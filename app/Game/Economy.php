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
    public static function cityYields(City $city, Empire $empire, Collection $cityTiles, array $empireEffects = [], bool $unhappy = false, int $pillaged = 0, bool $goldenAge = false): array
    {
        $focus = Rules::FOCUS[$city->focus ?? 'balanced'] ?? Rules::FOCUS['balanced'];
        $total = ['food' => 0, 'prod' => 0, 'gold' => 0, 'science' => 0, 'culture' => 1, 'happy' => 0];
        $worked = [];

        // City centre is always worked and gets a bonus so size-1 cities live.
        $centre = $cityTiles->firstWhere('id', $city->tile_id);
        if ($centre) {
            $y = Rules::tileYield($centre, $empire, $city);
            $total['food'] += max(2, $y['food'] + 1);
            $total['prod'] += max(1, $y['prod'] + 1);
            $total['gold'] += $y['gold'] + 1 + intdiv($city->population, 3); // taxes
            $total['science'] += $y['science'];
            $worked[] = $centre->id;
        }

        // Citizens work the best remaining tiles, food-weighted.
        $candidates = $cityTiles
            ->reject(fn (Tile $t) => $t->id === $city->tile_id)
            ->map(fn (Tile $t) => [$t, Rules::tileYield($t, $empire, $city)])
            ->sortByDesc(fn ($pair) => $pair[1]['food'] * $focus['food'] + $pair[1]['prod'] * $focus['prod'] + $pair[1]['gold'] * $focus['gold'] + $pair[1]['science'] * $focus['science'])
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
            if (isset($total[$k])) { // 'defense' is not a yield
                $total[$k] += $v;
            }
        }

        if ($unhappy) {
            $total['prod'] = (int) floor($total['prod'] * 0.75);
        }
        if ($goldenAge) {
            $total['prod'] = (int) ceil($total['prod'] * (1 + Rules::GOLDEN_AGE_BONUS));
            $total['gold'] = (int) ceil($total['gold'] * (1 + Rules::GOLDEN_AGE_BONUS));
        }
        // Barbarian camps nearby pillage the countryside.
        $total['food'] = max(0, $total['food'] - 2 * $pillaged);
        $total['pillaged'] = $pillaged;

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
    public static function happiness(Empire $empire, int $warsCount = 0): array
    {
        $effects = self::empireEffects($empire);
        $supply = Rules::BASE_HAPPINESS;
        $demand = $warsCount * Rules::WAR_WEARINESS;
        foreach ($empire->cities as $city) {
            $demand += max(0, $city->population - Rules::FREE_CITIZENS);
            $supply += Rules::CITY_HAPPINESS + ($effects['happy'] ?? 0);
            foreach ($city->buildings ?? [] as $b) {
                $supply += Rules::BUILDINGS[$b]['effects']['happy'] ?? 0;
            }
        }
        $balance = $supply - $demand;

        return ['supply' => $supply, 'demand' => $demand, 'balance' => $balance, 'unhappy' => $balance < 0, 'war_weariness' => $warsCount * Rules::WAR_WEARINESS];
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

    /** Total culture across the empire's cities. */
    public static function culture(Empire $empire): int
    {
        return (int) $empire->cities->sum('culture');
    }

    /** Number of empires $empire is at war with (ignoring the eliminated). */
    public static function warsCount(\App\Models\Game $game, Empire $empire): int
    {
        $n = 0;
        foreach ($game->empires as $other) {
            if ($other->id !== $empire->id && ! $other->eliminated && $game->atWar($empire->id, $other->id)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Peaceful empires whose territory touches ours. Trade income needs
     * Currency. Uses one pass over the tiles with a coordinate lookup.
     *
     * @return array<int> partner empire ids
     */
    public static function tradePartners(\App\Models\Game $game, Empire $empire, Collection $tiles): array
    {
        if (! $empire->knows('currency')) {
            return [];
        }
        $byCoord = [];
        foreach ($tiles as $t) {
            if ($t->empire_id) {
                $byCoord[$t->x.','.$t->y] = $t->empire_id;
            }
        }
        $partners = [];
        foreach ($tiles as $t) {
            if ($t->empire_id !== $empire->id) {
                continue;
            }
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $o = $byCoord[($t->x + $dx).','.($t->y + $dy)] ?? null;
                if ($o && $o !== $empire->id && ! $game->atWar($empire->id, $o)) {
                    $partners[$o] = true;
                }
            }
        }
        foreach (array_keys($partners) as $id) {
            $other = $game->empires->firstWhere('id', $id);
            if (! $other || $other->eliminated) {
                unset($partners[$id]);
            }
        }

        return array_keys($partners);
    }

    /** Tech tiers for display: depth in the prerequisite graph. */
    public static function techTiers(): array
    {
        $depth = [];
        $resolve = function (string $key) use (&$resolve, &$depth): int {
            if (isset($depth[$key])) {
                return $depth[$key];
            }
            $req = Rules::TECHS[$key]['requires'];
            $depth[$key] = $req ? 1 + max(array_map($resolve, $req)) : 0;

            return $depth[$key];
        };
        $tiers = [];
        foreach (array_keys(Rules::TECHS) as $key) {
            $tiers[$resolve($key)][] = $key;
        }
        ksort($tiers);

        return $tiers;
    }

    /** Camps within pillaging range (distance 2) of a city centre. */
    public static function campsNear(Tile $centre, Collection $tiles): int
    {
        return $tiles->filter(fn (Tile $t) => $t->camp && $t->distanceTo($centre) <= 2)->count();
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
            + intdiv($empire->army, 2)
            + intdiv(self::culture($empire), 20);
    }
}
