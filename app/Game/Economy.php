<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;
use App\Models\Tile;
use Illuminate\Support\Collection;

/**
 * Pure yield maths for a city and an empire. Given the city, its empire and
 * the tiles inside its borders, works out which tiles are worked and the
 * totals. Happiness and upkeep live here too.
 */
final class Economy
{
    /**
     * @param  Collection<int, Tile>  $cityTiles  tiles with city_id === $city->id
     * @param  bool  $unhappy  empire-wide unrest: no growth, production -25%
     * @return array{food:int,prod:int,gold:int,science:int,culture:int,happy:int,surplus:int,worked:array<int>}
     */
    public static function cityYields(City $city, Empire $empire, Collection $cityTiles, array $empireEffects = [], bool $unhappy = false): array
    {
        $total = ['food' => 0, 'prod' => 0, 'gold' => 0, 'science' => 0, 'culture' => 1, 'happy' => 0];
        $worked = [];

        // City centre is always worked and gets a bonus so size-1 cities live.
        $centre = $cityTiles->firstWhere('id', $city->tile_id);
        if ($centre) {
            $y = Rules::tileYield($centre, $empire);
            $total['food'] += max(2, $y['food'] + 1);
            $total['prod'] += max(1, $y['prod'] + 1);
            $total['gold'] += $y['gold'] + 1 + intdiv($city->population, 3); // taxes
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

        if ($unhappy) {
            $total['prod'] = (int) floor($total['prod'] * 0.75);
        }

        $total['surplus'] = $total['food'] - 2 * $city->population;
        if ($unhappy && $total['surplus'] > 0) {
            $total['surplus'] = 0;
        }
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
     * Happiness: supply from the base allowance plus buildings, demand from
     * population beyond the first citizen of each city. Negative balance
     * means unrest.
     *
     * @return array{supply:int,demand:int,balance:int,unhappy:bool}
     */
    public static function happiness(Empire $empire): array
    {
        $effects = self::empireEffects($empire);
        $supply = Rules::BASE_HAPPINESS;
        $demand = 0;
        foreach ($empire->cities as $city) {
            $demand += max(0, $city->population - Rules::FREE_CITIZENS);
            $supply += Rules::CITY_HAPPINESS + ($effects['happy'] ?? 0);
            foreach ($city->buildings ?? [] as $b) {
                $supply += Rules::BUILDINGS[$b]['effects']['happy'] ?? 0;
            }
        }
        $balance = $supply - $demand;

        return ['supply' => $supply, 'demand' => $demand, 'balance' => $balance, 'unhappy' => $balance < 0];
    }

    /** Gold per turn for buildings and the army. */
    public static function upkeep(Empire $empire): array
    {
        $count = 0;
        foreach ($empire->cities as $city) {
            foreach ($city->buildings ?? [] as $b) {
                if (! Rules::isWonder($b)) {
                    $count++;
                }
            }
        }
        $buildings = intdiv($count, Rules::BUILDINGS_PER_GOLD);
        $army = (int) ceil($empire->army / Rules::ARMY_PER_GOLD);

        return ['buildings' => $buildings, 'army' => $army, 'total' => $buildings + $army];
    }

    /**
     * What a city may build right now. Keys are build keys; values carry
     * name, cost, blurb and a `kind` of building|wonder|unit|project.
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
        foreach (Rules::UNITS as $key => $u) {
            if ($u['tech'] && ! $empire->knows($u['tech'])) {
                continue;
            }
            $options[$key] = ['name' => $u['name'], 'cost' => $u['cost'], 'blurb' => $u['blurb'], 'kind' => 'unit'];
        }
        foreach (Rules::PROJECTS as $key => $p) {
            $options[$key] = $p + ['kind' => 'project'];
        }

        return $options;
    }

    public static function buildCost(string $key): ?int
    {
        return Rules::BUILDINGS[$key]['cost'] ?? Rules::UNITS[$key]['cost'] ?? Rules::PROJECTS[$key]['cost'] ?? null;
    }

    public static function buildName(?string $key): string
    {
        if ($key === null) {
            return 'Nothing';
        }

        return Rules::BUILDINGS[$key]['name'] ?? Rules::UNITS[$key]['name'] ?? Rules::PROJECTS[$key]['name'] ?? ucfirst($key);
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
            + $tiles
            + intdiv($empire->army, 2);
    }
}
