<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;
use App\Models\Tile;
use Illuminate\Support\Collection;

/**
 * Pure yield maths for a city. Given the city, its empire and the tiles
 * inside its borders, works out which tiles are worked and the totals.
 */
final class Economy
{
    /**
     * @param  Collection<int, Tile>  $cityTiles  tiles with city_id === $city->id
     * @return array{food:int,prod:int,gold:int,science:int,culture:int,surplus:int,worked:array<int>}
     */
    public static function cityYields(City $city, Empire $empire, Collection $cityTiles, array $empireEffects = []): array
    {
        $total = ['food' => 0, 'prod' => 0, 'gold' => 0, 'science' => 0, 'culture' => 1];
        $worked = [];

        // City centre is always worked and gets a bonus so size-1 cities live.
        $centre = $cityTiles->firstWhere('id', $city->tile_id);
        if ($centre) {
            $y = Rules::tileYield($centre, $empire);
            $total['food'] += max(2, $y['food'] + 1);
            $total['prod'] += max(1, $y['prod'] + 1);
            $total['gold'] += $y['gold'];
            $total['science'] += $y['science'];
            $worked[] = $centre->id;
        }

        // Citizens work the best remaining tiles, food-weighted.
        $candidates = $cityTiles
            ->reject(fn (Tile $t) => $t->id === $city->tile_id)
            ->map(fn (Tile $t) => [$t, Rules::tileYield($t, $empire)])
            ->sortByDesc(fn ($pair) => $pair[1]['food'] * 2 + $pair[1]['prod'] * 1.5 + $pair[1]['gold'] + $pair[1]['science'])
            ->take($city->population);

        foreach ($candidates as [$t, $y]) {
            foreach ($y as $k => $v) {
                $total[$k] += $v;
            }
            $worked[] = $t->id;
        }

        // Citizens themselves produce a trickle of science.
        $total['science'] += intdiv($city->population + 1, 2);

        foreach ($city->buildings ?? [] as $b) {
            foreach (Rules::BUILDINGS[$b]['effects'] ?? [] as $k => $v) {
                $total[$k] += $v;
            }
        }
        foreach ($empireEffects as $k => $v) {
            $total[$k] += $v;
        }

        $total['surplus'] = $total['food'] - 2 * $city->population;
        $total['worked'] = $worked;

        return $total;
    }

    /** Summed empire_effects of all wonders the empire owns. */
    public static function empireEffects(Empire $empire): array
    {
        $effects = [];
        foreach ($empire->wonders() as $w) {
            foreach (Rules::BUILDINGS[$w]['empire_effects'] ?? [] as $k => $v) {
                $effects[$k] = ($effects[$k] ?? 0) + $v;
            }
        }

        return $effects;
    }

    /**
     * What a city may build right now. Keys are build keys; values carry
     * name, cost, blurb and a `kind` of building|wonder|project.
     */
    public static function buildOptions(City $city, Empire $empire, array $wondersBuilt): array
    {
        $options = [];
        foreach (Rules::BUILDINGS as $key => $b) {
            if ($city->has($key)) {
                continue;
            }
            if ($b['tech'] && ! $empire->knows($b['tech'])) {
                continue;
            }
            if ($b['requires'] && ! $city->has($b['requires'])) {
                continue;
            }
            if ($b['wonder'] && in_array($key, $wondersBuilt, true)) {
                continue;
            }
            $options[$key] = ['name' => $b['name'], 'cost' => $b['cost'], 'blurb' => $b['blurb'], 'kind' => $b['wonder'] ? 'wonder' : 'building'];
        }
        foreach (Rules::PROJECTS as $key => $p) {
            $options[$key] = $p + ['kind' => 'project'];
        }

        return $options;
    }

    public static function buildCost(string $key): ?int
    {
        return Rules::BUILDINGS[$key]['cost'] ?? Rules::PROJECTS[$key]['cost'] ?? null;
    }

    public static function buildName(?string $key): string
    {
        if ($key === null) {
            return 'Nothing';
        }

        return Rules::BUILDINGS[$key]['name'] ?? Rules::PROJECTS[$key]['name'] ?? ucfirst($key);
    }

    public static function score(Empire $empire, int $tiles): int
    {
        $pop = 0;
        $buildings = 0;
        $wonders = 0;
        foreach ($empire->cities as $c) {
            $pop += $c->population;
            foreach ($c->buildings ?? [] as $b) {
                Rules::isWonder($b) ? $wonders++ : $buildings++;
            }
        }

        return $pop * 3
            + $empire->cities->count() * 10
            + count($empire->technologies ?? []) * 5
            + $buildings * 2
            + $wonders * 25
            + $tiles;
    }
}
