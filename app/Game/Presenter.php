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

    /** Inline SVG line chart of score per empire over time. */
    private function chart(Game $game, $empiresById): ?string
    {
        $history = $game->history ?? [];
        if (count($history) < 2) {
            return null;
        }
        $w = 320;
        $h = 110;
        $pad = 4;
        $maxTurn = max(1, end($history)[0]);
        $maxScore = 1;
        foreach ($history as [, $scores]) {
            $maxScore = max($maxScore, max($scores));
        }
        $paths = [];
        foreach ($empiresById as $id => $e) {
            $pts = [];
            foreach ($history as [$turn, $scores]) {
                $v = $scores[(string) $id] ?? 0;
                $x = $pad + ($w - 2 * $pad) * $turn / $maxTurn;
                $y = $h - $pad - ($h - 2 * $pad) * $v / $maxScore;
                $pts[] = round($x, 1).','.round($y, 1);
            }
            $paths[] = sprintf('<polyline points="%s" fill="none" stroke="%s" stroke-width="%s" stroke-linejoin="round"/>', implode(' ', $pts), e($e->color), $e->is_ai ? 1.5 : 2.5);
        }

        return sprintf('<svg viewBox="0 0 %d %d" class="h-auto w-full" role="img" aria-label="Score over time"><line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#44403c"/>%s<text x="%d" y="%d" font-size="8" fill="#a8a29e">%d</text></svg>',
            $w, $h, $pad, $h - $pad, $w - $pad, $h - $pad, implode('', $paths), $pad + 2, 10, $maxScore);
    }

    public function present(Game $game, ?int $selectedTileId): array
    {
        $game->load(['empires.cities', 'cities', 'tiles']);
        $tiles = $game->tiles;
        $player = $game->playerEmpire();
        $effects = Economy::empireEffects($player);
        $happiness = Economy::happiness($player, Economy::warsCount($game, $player));
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
        $tradePartners = Economy::tradePartners($game, $player, $tiles);
        $totals['gold'] += count($tradePartners) * Rules::TRADE_GOLD_PER_PARTNER;
        foreach ($player->cities as $city) {
            $centre = $tiles->firstWhere('id', $city->tile_id);
            $y = Economy::cityYields($city, $player, $tiles->where('city_id', $city->id), $effects, $happiness['unhappy'], $centre ? Economy::campsNear($centre, $tiles) : 0);
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
                'river' => $selected->river && $selected->isLand(),
                'yield' => Rules::tileYield($selected, $player, $selected->city_id ? $game->cities->firstWhere('id', $selected->city_id) : null),
                'campProblem' => $selected->camp ? $this->actions->campProblem($player, $selected, $tiles) : null,
                'owner' => $owner,
                'ownerCity' => $selected->city_id ? $game->cities->firstWhere('id', $selected->city_id) : null,
                'worked' => in_array($selected->id, $worked, true),
                'city' => $selectedCity,
                'foundingProblem' => $selectedCity ? null : $this->actions->foundingProblem($game, $player, $selected, $tiles),
            ];
            if ($selectedCity && $selectedCity->empire_id !== $player->id && $owner) {
                $selectedInfo['skyline'] = CitySvg::render($selectedCity, $owner);
                $defense = Rules::cityDefense($selectedCity, $this->actions->garrison($owner), Economy::empireEffects($owner)['defense'] ?? 0);
                $selectedInfo['defense'] = $defense;
                $selectedInfo['attackProblem'] = $this->actions->attackProblem($player, $selectedCity, $tiles, $game, new TileIndex($tiles));
                $selectedInfo['atWar'] = $game->atWar($player->id, $owner->id);
                $selectedInfo['warProblem'] = $this->actions->warProblem($game, $player, $owner);
                $selectedInfo['attackOdds'] = $player->army > $defense ? 'win' : ($player->army > $defense * 0.7 ? 'close' : 'lose');
            }
            if ($selectedCity && $selectedCity->empire_id === $player->id) {
                $y = $cityYields[$selectedCity->id];
                $cost = Economy::buildCost($selectedCity->current_build ?? '');
                $remaining = $cost !== null ? max(0, $cost - $selectedCity->production_stored) : null;
                $selectedInfo['cityYields'] = $y;
                $selectedInfo['skyline'] = CitySvg::render($selectedCity, $player);
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
            'culture' => Economy::culture($e),
            'relation' => $e->id === $player->id ? null : $game->relation($player->id, $e->id),
            'warProblem' => $e->id === $player->id ? null : $this->actions->warProblem($game, $player, $e),
            'peaceLikely' => $e->id !== $player->id && $game->atWar($player->id, $e->id) && $this->actions->peaceAcceptable($game, $player, $e),
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
            'minimap' => $this->mapSvg->minimap($game, $tiles, $empiresById, $citiesByTile),
            'empiresById' => $empiresById,
            'citiesByTile' => $citiesByTile,
            'worked' => array_flip($worked),
            'totals' => $totals,
            'happiness' => $happiness,
            'upkeep' => $upkeep,
            'netGold' => $totals['gold'] - $upkeep['total'],
            'culture' => Economy::culture($player),
            'tradePartners' => collect($tradePartners)->map(fn ($id) => $empiresById->get($id))->filter()->values(),
            'tradeGold' => count($tradePartners) * Rules::TRADE_GOLD_PER_PARTNER,
            'techTiers' => Economy::techTiers(),
            'cityYields' => $cityYields,
            'settlersWaiting' => $player->settlers > 0,
            'researchUnset' => $player->current_tech === null && (bool) $player->availableTechs(),
            'cultureGoal' => Rules::cultureGoal($game),
            'wars' => Economy::warsCount($game, $player),
            'research' => $research,
            'availableTechs' => $player->availableTechs(),
            'idleCities' => $idleCities,
            'selected' => $selectedInfo,
            'rivals' => $rivals,
            'landShare' => round(100 * ($ownedBy[$player->id] ?? 0) / $landTotal),
            'dominationShare' => (int) (Rules::DOMINATION_SHARE * 100),
            'winner' => $game->winner_empire_id ? $empiresById->get($game->winner_empire_id) : null,
            'chart' => $this->chart($game, $empiresById),
            'toasts' => collect($game->events ?? [])->map(fn ($e) => ['text' => $e, 'kind' => Rules::eventKind($e)])->values(),
            'difficulty' => Rules::DIFFICULTIES[$game->difficulty ?? 'prince'],
        ];
    }
}
