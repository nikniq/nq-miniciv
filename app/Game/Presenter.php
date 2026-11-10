<?php

namespace App\Game;

use App\Models\Game;
use App\Models\Tile;

/**
 * Builds everything the game screen needs in one pass so the Blade view
 * stays free of rule logic.
 */
final class Presenter
{
    public function __construct(private Actions $actions, private MapSvg $mapSvg) {}

    public function present(Game $game, ?int $selectedTileId): array
    {
        $game->load(['empires.cities', 'cities', 'tiles']);
        $tiles = $game->tiles;
        $player = $game->playerEmpire();
        $effects = Economy::empireEffects($player);
        $happiness = Economy::happiness($player);
        $upkeep = Economy::upkeep($player);

        $wondersBuilt = [];
        foreach ($game->cities as $c) {
            foreach ($c->buildings ?? [] as $b) {
                if (Rules::isWonder($b)) {
                    $wondersBuilt[] = $b;
                }
            }
        }

        // Per-city yields for the player, and empire totals.
        $cityYields = [];
        $totals = ['food' => 0, 'prod' => 0, 'gold' => 0, 'science' => 0];
        $worked = [];
        foreach ($player->cities as $city) {
            $y = Economy::cityYields($city, $player, $tiles->where('city_id', $city->id), $effects, $happiness['unhappy']);
            $cityYields[$city->id] = $y;
            $totals['gold'] += $y['gold'] + ($city->current_build === 'wealth' ? $y['prod'] : 0);
            $totals['science'] += $y['science'];
            $totals['food'] += $y['food'];
            $totals['prod'] += $y['prod'];
            $worked = array_merge($worked, $y['worked']);
        }

        $empiresById = $game->empires->keyBy('id');
        $citiesByTile = $game->cities->keyBy('tile_id');
        $landTotal = max(1, $tiles->filter(fn (Tile $t) => $t->isLand())->count());
        $ownedBy = $tiles->filter(fn (Tile $t) => $t->isLand() && $t->empire_id)->countBy('empire_id');

        // Map rows.
        $grid = [];
        foreach ($tiles->sortBy([['y', 'asc'], ['x', 'asc']]) as $t) {
            $grid[$t->y][$t->x] = $t;
        }

        // Selected tile details.
        $selected = $selectedTileId ? $tiles->firstWhere('id', $selectedTileId) : null;
        $selectedCity = $selected ? $citiesByTile->get($selected->id) : null;
        $selectedInfo = null;
        if ($selected) {
            $owner = $selected->empire_id ? $empiresById->get($selected->empire_id) : null;
            $selectedInfo = [
                'tile' => $selected,
                'terrain' => Rules::TERRAIN[$selected->terrain],
                'resource' => $selected->resource ? Rules::RESOURCES[$selected->resource] : null,
                'yield' => Rules::tileYield($selected, $player),
                'owner' => $owner,
                'ownerCity' => $selected->city_id ? $game->cities->firstWhere('id', $selected->city_id) : null,
                'worked' => in_array($selected->id, $worked, true),
                'city' => $selectedCity,
                'foundingProblem' => $selectedCity ? null : $this->actions->foundingProblem($game, $player, $selected, $tiles),
            ];
            if ($selectedCity && $selectedCity->empire_id !== $player->id && $owner) {
                $defense = Rules::cityDefense($selectedCity, $this->actions->garrison($owner));
                $selectedInfo['defense'] = $defense;
                $selectedInfo['attackProblem'] = $this->actions->attackProblem($player, $selectedCity, $tiles);
                $selectedInfo['attackOdds'] = $player->army > $defense ? 'win' : ($player->army > $defense * 0.7 ? 'close' : 'lose');
            }
            if ($selectedCity && $selectedCity->empire_id === $player->id) {
                $y = $cityYields[$selectedCity->id];
                $cost = Economy::buildCost($selectedCity->current_build ?? '');
                $remaining = $cost !== null ? max(0, $cost - $selectedCity->production_stored) : null;
                $selectedInfo['cityYields'] = $y;
                $selectedInfo['buildOptions'] = Economy::buildOptions($selectedCity, $player, $wondersBuilt);
                $selectedInfo['buildCost'] = $cost;
                $selectedInfo['turnsToBuild'] = ($remaining !== null && $y['prod'] > 0) ? (int) ceil($remaining / $y['prod']) : null;
                $selectedInfo['buyPrice'] = $remaining ? $remaining * Rules::BUY_RATE : null;
                $selectedInfo['turnsToGrow'] = $y['surplus'] > 0
                    ? (int) ceil(($selectedCity->foodToGrow() - $selectedCity->food_stored) / $y['surplus'])
                    : null;
            }
        }

        // Cities producing Wealth although something useful could be built.
        $idleCities = $player->cities->filter(function ($c) use ($player, $wondersBuilt) {
            if ($c->current_build !== 'wealth' && $c->current_build !== null) {
                return false;
            }
            $opts = Economy::buildOptions($c, $player, $wondersBuilt);

            return (bool) array_filter($opts, fn ($o) => $o['kind'] === 'building');
        })->values();

        $research = null;
        if ($player->current_tech) {
            $t = Rules::TECHS[$player->current_tech];
            $remaining = max(0, $t['cost'] - $player->research_progress);
            $research = [
                'key' => $player->current_tech,
                'name' => $t['name'],
                'cost' => $t['cost'],
                'progress' => min($player->research_progress, $t['cost']),
                'turns' => $totals['science'] > 0 ? (int) ceil($remaining / $totals['science']) : null,
            ];
        }

        $rivals = $game->empires->map(fn ($e) => [
            'empire' => $e,
            'army' => $e->army,
            'cities' => $e->cities->count(),
            'pop' => $e->cities->sum('population'),
            'tiles' => $ownedBy[$e->id] ?? 0,
            'share' => round(100 * ($ownedBy[$e->id] ?? 0) / $landTotal),
            'techs' => count($e->technologies ?? []),
            'score' => $e->score ?: Economy::score($e, $ownedBy[$e->id] ?? 0),
        ])->sortByDesc('score')->values();

        $mapSvg = $this->mapSvg->render($game, $tiles, $empiresById, $citiesByTile, array_flip($worked), $selected?->id, route('games.show', $game));

        return [
            'game' => $game,
            'player' => $player,
            'grid' => $grid,
            'mapSvg' => $mapSvg,
            'empiresById' => $empiresById,
            'citiesByTile' => $citiesByTile,
            'worked' => array_flip($worked),
            'totals' => $totals,
            'happiness' => $happiness,
            'upkeep' => $upkeep,
            'netGold' => $totals['gold'] - $upkeep['total'],
            'research' => $research,
            'availableTechs' => $player->availableTechs(),
            'idleCities' => $idleCities,
            'selected' => $selectedInfo,
            'rivals' => $rivals,
            'landShare' => round(100 * ($ownedBy[$player->id] ?? 0) / $landTotal),
            'dominationShare' => (int) (Rules::DOMINATION_SHARE * 100),
            'winner' => $game->winner_empire_id ? $empiresById->get($game->winner_empire_id) : null,
        ];
    }
}
