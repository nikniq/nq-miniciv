<?php

namespace App\Game;

use App\Models\Empire;
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
    /**
     * Where the player stands among the living empires, metric by metric.
     *
     * @return array<int, array{label: string, icon: string, mine: int, rank: int, of: int, best: int}>
     */
    private function demographics(Game $game, Empire $player, $ownedBy): array
    {
        $alive = $game->empires->filter(fn ($e) => ! $e->eliminated)->values();
        $metrics = [
            ['Population', '👥', fn ($e) => (int) $e->cities->sum('population')],
            ['Land', '🗺️', fn ($e) => (int) ($ownedBy[$e->id] ?? 0)],
            ['Army', '⚔️', fn ($e) => $e->strength()],
            ['Culture', '🎭', fn ($e) => Economy::culture($e)],
            ['Technologies', '🔬', fn ($e) => count($e->technologies ?? [])],
            ['Treasury', '💰', fn ($e) => (int) $e->gold],
        ];
        $out = [];
        foreach ($metrics as [$label, $icon, $f]) {
            $values = $alive->mapWithKeys(fn ($e) => [$e->id => $f($e)]);
            $mine = $values[$player->id] ?? 0;
            $out[] = ['label' => $label, 'icon' => $icon, 'mine' => $mine, 'rank' => 1 + $values->filter(fn ($v) => $v > $mine)->count(), 'of' => $alive->count(), 'best' => (int) $values->max()];
        }

        return $out;
    }

    /**
     * What will happen when the turn ends: completions, growth, discoveries
     * and deadlines. Each line is [icon, text, tile id or null].
     *
     * @return array<int, array{0: string, 1: string, 2: ?int}>
     */
    private function briefing(Game $game, Empire $player, array $cityYields, array $totals): array
    {
        $out = [];
        foreach ($player->cities as $city) {
            $y = $cityYields[$city->id] ?? null;
            if (! $y) {
                continue;
            }
            $key = $city->current_build;
            $cost = $key ? Economy::buildCost($key) : null;
            if ($cost !== null && $city->production_stored + $y['prod'] >= $cost) {
                $out[] = ['🏗️', "{$city->name} completes ".Economy::buildName($key), $city->tile_id];
            }
            if ($y['surplus'] > 0 && $city->food_stored + $y['surplus'] >= $city->foodToGrow() && $city->population < Economy::sizeCap($city)) {
                $out[] = ['🌱', "{$city->name} grows to size ".($city->population + 1), $city->tile_id];
            } elseif ($y['surplus'] < 0 && $city->food_stored + $y['surplus'] < 0 && $city->population > 1) {
                $out[] = ['⚠️', "{$city->name} is starving and will shrink", $city->tile_id];
            }
        }
        if ($player->current_tech && $player->research_progress + $totals['science'] >= Rules::TECHS[$player->current_tech]['cost']) {
            $out[] = ['🔬', 'You discover '.Rules::TECHS[$player->current_tech]['name'], null];
        }
        if ($player->golden_age_until === $game->turn) {
            $out[] = ['✦', 'The Golden Age ends', null];
        }
        foreach ($game->quests ?? [] as $q) {
            $left = $q['until'] - $game->turn;
            if ($left <= 2 && ($asker = $game->empires->firstWhere('id', $q['empire']))) {
                $out[] = ['📜', "{$asker->name}'s favour is due in {$left} ".($left === 1 ? 'turn' : 'turns'), null];
            }
        }

        return $out;
    }

    /**
     * First steps for a new ruler. Each item ticks itself from game state.
     *
     * @return array<int, array{done: bool, text: string, hint: string}>|null
     */
    private function checklist(Game $game, Empire $player): ?array
    {
        $cities = $player->cities;
        $items = [
            [$player->current_tech !== null || count($player->technologies ?? []) > 0, 'Choose a technology to research', 'Research panel, or click a blue node in the Tech tree.'],
            [$cities->count() >= 2, 'Found a second city', 'You start with settlers: press Found at best site, or click a green tile.'],
            [$player->army > 0 || $cities->contains(fn ($c) => isset(Rules::UNITS[$c->current_build ?? ''])), 'Train a unit for the army', 'Warriors guard against barbarian war bands. Pick one in a city\'s build menu.'],
            [$cities->contains(fn ($c) => ! empty($c->queue) || count($c->buildings ?? []) > 0), 'Complete or queue a building', 'Use +Q in the city panel to line up builds.'],
            [Fog::percent($game, $player) >= 15, 'Explore 15% of the world', 'Send scouts from a city, or let your borders grow.'],
            [(int) $player->veterancy > 0, 'Win a fight: clear a camp or repel a raid', 'An army of '.Rules::CAMP_CLEAR_ARMY.'+ can clear a ☠ camp next to your land.'],
            [$cities->contains(fn ($c) => (bool) $c->trade_to), 'Open a trade route', 'Research Currency, then send a caravan from a city panel.'],
        ];
        $out = array_map(fn ($i) => ['done' => $i[0], 'text' => $i[1], 'hint' => $i[2]], $items);

        return collect($out)->every('done') ? null : $out;
    }

    /** "x,y,w,h" in map units around the explored tiles, or null when all is known. */
    private function knownBox($tiles, ?array $visible): ?string
    {
        if ($visible === null) {
            return null;
        }
        $minX = $minY = PHP_INT_MAX;
        $maxX = $maxY = -1;
        foreach ($tiles as $t) {
            if (isset($visible[$t->id])) {
                $minX = min($minX, $t->x);
                $maxX = max($maxX, $t->x);
                $minY = min($minY, $t->y);
                $maxY = max($maxY, $t->y);
            }
        }
        if ($maxX < 0) {
            return null;
        }
        $s = MapSvg::TILE;

        return implode(',', [$minX * $s, $minY * $s, ($maxX - $minX + 1) * $s, ($maxY - $minY + 1) * $s]);
    }

    /** A line per empire for one history series: 1 score, 2 land, 3 culture. */
    private function chart(Game $game, $empiresById, int $series = 1): ?string
    {
        $history = array_values(array_filter(
            array_map(fn ($row) => isset($row[$series]) ? [$row[0], $row[$series]] : null, $game->history ?? []),
        ));
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
        $visible = Fog::visibleIds($game, $player, $tiles);
        $effects = Economy::empireEffects($player);
        $difficultySpec = Rules::DIFFICULTIES[$game->difficulty ?? 'prince'];
        $happiness = Economy::happiness($player, Economy::warsCount($game, $player), $difficultySpec['player_happy']);
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
        $totals['gold'] += Economy::tradeGold($game, $player, $tiles);
        foreach ($player->cities as $city) {
            $centre = $tiles->firstWhere('id', $city->tile_id);
            $y = Economy::cityYields($city, $player, $tiles->where('city_id', $city->id), $effects, $happiness['unhappy'], $centre ? Economy::campsNear($centre, $tiles) : 0, $player->inGoldenAge($game->turn));
            $cityYields[$city->id] = $y;
            $totals['gold'] += $y['gold'] + ($city->current_build === 'wealth' ? $y['prod'] : 0);
            $totals['science'] += $y['science'];
            $totals['food'] += $y['food'];
            $totals['prod'] += $y['prod'];
            $worked = array_merge($worked, $y['worked']);
        }
        // Trade routes: gold and science on top of the cities' own yields.
        $routes = [];
        $allCities = $game->cities->keyBy('id');
        foreach ($player->cities as $city) {
            $to = $city->trade_to ? $allCities->get($city->trade_to) : null;
            if ($to) {
                $ry = Economy::routeYield($city, $to, $tiles->firstWhere('id', $city->tile_id), $tiles->firstWhere('id', $to->tile_id), $effects['route_gold'] ?? 0);
                $routes[$city->id] = ['to' => $to, 'yield' => $ry];
                $totals['gold'] += $ry['gold'];
                $totals['science'] += $ry['science'];
            }
        }

        $empiresById = $game->empires->keyBy('id');
        $citiesByTile = $game->cities->keyBy('tile_id');
        $landTotal = max(1, $tiles->filter(fn (Tile $t) => $t->isLand())->count());
        $ownedBy = $tiles->filter(fn (Tile $t) => $t->isLand() && $t->empire_id)->countBy('empire_id');

        // Map rows, row-major. Keys are sorted afterwards: far cheaper than
        // sorting thousands of models with a two-key comparator.
        $grid = [];
        foreach ($tiles as $t) {
            $grid[$t->y][$t->x] = $t;
        }
        ksort($grid);
        foreach ($grid as &$row) {
            ksort($row);
        }
        unset($row);

        // Selected tile details.
        $selected = $selectedTileId ? $tiles->firstWhere('id', $selectedTileId) : null;
        $selectedCity = $selected ? $citiesByTile->get($selected->id) : null;
        $selectedInfo = null;
        if ($selected) {
            $owner = $selected->empire_id ? $empiresById->get($selected->empire_id) : null;
            $selectedInfo = $visible !== null && ! isset($visible[$selected->id]) ? ['tile' => $selected, 'city' => null, 'unexplored' => true] : [
                'tile' => $selected,
                'terrain' => Rules::TERRAIN[$selected->terrain],
                'resource' => $selected->resource ? Rules::RESOURCES[$selected->resource] : null,
                'river' => $selected->river && $selected->isLand(),
                'feature' => $selected->feature ? Rules::NATURAL_WONDERS[$selected->feature] : null,
                'yield' => Rules::tileYield($selected, $player, $selected->city_id ? $game->cities->firstWhere('id', $selected->city_id) : null),
                'campProblem' => $selected->camp ? $this->actions->campProblem($player, $selected, $tiles) : null,
                'owner' => $owner,
                'ownerCity' => $selected->city_id ? $game->cities->firstWhere('id', $selected->city_id) : null,
                'worked' => in_array($selected->id, $worked, true),
                'city' => $selectedCity,
                'foundingProblem' => $selectedCity ? null : $this->actions->foundingProblem($game, $player, $selected, $tiles),
            ];
            if (isset($selectedInfo['unexplored'])) {
                $selectedCity = null;
            }
            if ($selectedCity && $selectedCity->empire_id !== $player->id && $owner) {
                $selectedInfo['skyline'] = CitySvg::render($selectedCity, $owner);
                $defense = Rules::cityDefense($selectedCity, $this->actions->garrison($owner), Economy::empireEffects($owner)['defense'] ?? 0);
                $selectedInfo['defense'] = $defense;
                $selectedInfo['attackProblem'] = $this->actions->attackProblem($player, $selectedCity, $tiles, $game, new TileIndex($tiles));
                $selectedInfo['atWar'] = $game->atWar($player->id, $owner->id);
                $selectedInfo['raidProblem'] = $this->actions->raidProblem($game, $player, $selectedCity, $tiles);
                $selectedInfo['raidGold'] = min($owner->gold, 10 + 3 * $selectedCity->population);
                $selectedInfo['warProblem'] = $this->actions->warProblem($game, $player, $owner);
                $selectedInfo['attackOdds'] = $player->strength() > $defense ? 'win' : ($player->strength() > $defense * 0.7 ? 'close' : 'lose');
            }
            if ($selectedCity && $selectedCity->empire_id === $player->id) {
                $y = $cityYields[$selectedCity->id];
                $cost = Economy::buildCost($selectedCity->current_build ?? '');
                $remaining = $cost !== null ? max(0, $cost - $selectedCity->production_stored) : null;
                $selectedInfo['cityYields'] = $y;
                $selectedInfo['skyline'] = CitySvg::render($selectedCity, $player);
                $selectedInfo['sizeCap'] = Economy::sizeCap($selectedCity);
                $selectedInfo['scoutable'] = Fog::wouldReveal($game, $player, $selected->x, $selected->y, Rules::SCOUT_RADIUS);
                $selectedInfo['route'] = $routes[$selectedCity->id] ?? null;
                $selectedInfo['routeOptions'] = $player->knows('currency') ? $this->actions->routeOptions($game, $player, $selectedCity, $tiles) : null;
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

        // Advisor: short, situational tips. The page hides dismissed ids.
        $tips = [];
        // Losing a wonder race wastes the production: warn first, above everything else.
        foreach ($player->cities as $mine) {
            $w = $mine->current_build;
            if (! $w || ! Rules::isWonder($w)) {
                continue;
            }
            $cost = Rules::BUILDINGS[$w]['cost'];
            $rival = $game->cities->first(fn ($c) => $c->empire_id !== $player->id && $c->current_build === $w && $c->production_stored >= 0.75 * $cost);
            if ($rival && $rival->production_stored > $mine->production_stored) {
                $owner = $game->empires->firstWhere('id', $rival->empire_id);
                $tips[] = ['id' => "race-{$w}-{$mine->id}-".intdiv($game->turn, 5), 'text' => "{$owner?->name} is ".(int) round(100 * $rival->production_stored / $cost).'% through the '.Rules::BUILDINGS[$w]['name']." that {$mine->name} is building (".(int) round(100 * $mine->production_stored / $cost).'%). Buy it now or switch before the race is lost.'];
            }
        }
        if ($player->current_tech === null && $player->availableTechs()) {
            $tips[] = ['id' => 'pick-research-'.$game->turn, 'text' => 'Choose a technology. Agriculture (Granary, Farms) or Mining (Workshop, Mine) are strong first picks.'];
        }
        if ($game->turn >= 5 && $player->army === 0) {
            $tips[] = ['id' => 'no-army', 'text' => 'You have no army. A few Warriors deter neighbours and let you clear barbarian camps for gold.'];
        }
        if ($happiness['unhappy']) {
            $tips[] = ['id' => 'unhappy-'.intdiv($game->turn, 10), 'text' => 'Your people are restless. Monuments and Temples add happiness; so does avoiding wars and letting cities stay a little smaller.'];
        }
        if ($player->gold >= 250 && $game->turn > 10) {
            $tips[] = ['id' => 'rich-'.intdiv($game->turn, 15), 'text' => 'You have a large treasury. Rush-buy a building or wonder with Buy now before a rival finishes it.'];
        }
        if ($player->knows('currency') && count($tradePartners) === 0 && $game->turn > 20) {
            $tips[] = ['id' => 'trade-'.intdiv($game->turn, 20), 'text' => 'Currency pays 3 gold per peaceful neighbour whose land touches yours. Expand toward a neighbour and keep the peace.'];
        }
        if ($player->cities->count() === 1 && $game->turn >= 12) {
            $tips[] = ['id' => 'one-city-'.intdiv($game->turn, 12), 'text' => 'One city will not win. Build Settlers (city size 2+) and claim land while it is free.'];
        }
        // One tip while the settlers banner is showing, two otherwise.
        $tips = array_slice($tips, 0, $player->settlers > 0 ? 1 : 2);

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
            'attitude' => $e->id === $player->id ? null : $game->attitude($player->id, $e->id),
            'attitudeLabel' => $e->id === $player->id ? null : Rules::attitudeLabel($game->attitude($player->id, $e->id)),
            'personality' => $e->is_ai ? Rules::personality($e->name) : null,
            'trades' => $e->id === $player->id ? [] : $this->actions->techTradeOffers($game, $player, $e),
            'allied' => $e->id !== $player->id && $game->allied($player->id, $e->id),
            'canAlly' => $e->id !== $player->id && $this->actions->allianceProblem($game, $player, $e) === null,
            'quest' => ($q = collect($game->quests ?? [])->firstWhere('empire', $e->id)) ? ['text' => Quests::describe($game, $q, $tiles), 'left' => $q['until'] - $game->turn] : null,
            'theirAllies' => $e->id === $player->id ? [] : $game->alliesOf($e->id, $player->id),
            'canTribute' => $e->id !== $player->id && ! $game->atWar($player->id, $e->id) && $player->army >= 2 * $e->army + 5,
            'warProblem' => $e->id === $player->id ? null : $this->actions->warProblem($game, $player, $e),
            'peaceLikely' => $e->id !== $player->id && $game->atWar($player->id, $e->id) && $this->actions->peaceAcceptable($game, $player, $e),
            'cities' => $e->cities->count(),
            'pop' => $e->cities->sum('population'),
            'tiles' => $ownedBy[$e->id] ?? 0,
            'share' => round(100 * ($ownedBy[$e->id] ?? 0) / $landTotal),
            'techs' => count($e->technologies ?? []),
            'score' => $e->score ?: Economy::score($e, $ownedBy[$e->id] ?? 0),
        ])->sortByDesc('score')->values();

        // Founding help: every legal site, and the best of them.
        $foundable = [];
        $bestSite = null;
        if ($player->settlers > 0 && $game->isActive()) {
            foreach ($tiles as $t) {
                if ($t->canSettle() && ! $t->camp && $this->actions->foundingProblem($game, $player, $t, $tiles) === null) {
                    $foundable[$t->id] = true;
                }
            }
            $bestSite = $this->actions->bestSite($game, $player, $tiles, 8);
        }

        $mapSvg = $this->mapSvg->render($game, $tiles, $empiresById, $citiesByTile, array_flip($worked), $selected?->id, route('games.show', $game), $foundable, $bestSite?->id, $visible, $game->raids ?? [], $player->inGoldenAge($game->turn) ? $player->id : null);

        return [
            'game' => $game,
            'player' => $player,
            'grid' => $grid,
            'mapSvg' => $mapSvg,
            'minimap' => $this->mapSvg->minimap($game, $tiles, $empiresById, $citiesByTile, $visible),
            'explored' => Fog::percent($game, $player),
            'known' => $this->knownBox($tiles, $visible),
            'empiresById' => $empiresById,
            'citiesByTile' => $citiesByTile,
            'worked' => array_flip($worked),
            'totals' => $totals,
            'happiness' => $happiness,
            'upkeep' => $upkeep,
            'netGold' => $totals['gold'] - $upkeep['total'],
            'culture' => Economy::culture($player),
            'tradePartners' => collect($tradePartners)->map(fn ($id) => $empiresById->get($id))->filter()->values(),
            'tradeGold' => Economy::tradeGold($game, $player, $tiles),
            'government' => Economy::government($player) + ['key' => $player->government ?? 'despotism'],
            'army' => [
                'strength' => $player->army,
                'fighting' => $player->strength(),
                'rank' => $player->rankName(),
                'rankNum' => $player->rank(),
                'xp' => (int) $player->veterancy,
                'nextAt' => $player->nextRankAt(),
                'upgrades' => $player->upgrades(),
                'units' => collect($player->units ?? [])->map(fn ($n, $key) => ['key' => $key, 'n' => $n] + Rules::UNITS[$key])->sortByDesc('strength')->values(),
                'untracked' => max(0, $player->army - collect($player->units ?? [])->sum(fn ($n, $key) => $n * Rules::UNITS[$key]['strength'])),
                'garrison' => $player->garrison(),
                'upkeep' => $upkeep['army'],
                'inProduction' => $player->cities->filter(fn ($c) => isset(Rules::UNITS[$c->current_build ?? '']))->map(fn ($c) => ['city' => $c, 'unit' => Rules::UNITS[$c->current_build]])->values(),
            ],
            'governments' => Economy::availableGovernments($player),
            'raidsIncoming' => collect($game->raids ?? [])->map(function ($r) use ($player) {
                $city = $player->cities->firstWhere('id', $r['city']);
                if (! $city) {
                    return null;
                }
                $defense = Rules::cityDefense($city, $this->actions->garrison($player), Economy::empireEffects($player)['defense'] ?? 0);

                return $r + ['cityModel' => $city, 'defense' => $defense, 'holds' => $defense >= $r['strength']];
            })->filter()->values(),
            'briefing' => $game->isActive() ? $this->briefing($game, $player, $cityYields, $totals) : [],
            'demographics' => $this->demographics($game, $player, $ownedBy),
            'checklist' => $game->turn <= 40 && $game->isActive() ? $this->checklist($game, $player) : null,
            'pantheonReady' => ! $player->belief && $game->isActive() && Economy::culture($player) >= Rules::PANTHEON_CULTURE,
            'choice' => $game->choice ? Choices::describe($game, $game->choice) : null,
            'greatPeople' => array_count_values($player->great_people ?? []),
            'greatProgress' => ['have' => $player->greatPointsTotal(), 'need' => $player->greatPointsNeeded(), 'kinds' => $player->great_progress ?? []],
            'anarchy' => $player->inAnarchy($game->turn) ? $player->anarchy_until - $game->turn + 1 : 0,
            'achievements' => $game->user?->achievements ?? [],
            'techTiers' => Economy::techTiers(),
            'techTreeSvg' => DiagramSvg::techTree($player, Economy::techTiers(), $player->availableTechs(), $game->isActive(), $totals['science']),
            'relationsSvg' => DiagramSvg::relations($game, $game->empires, $player),
            'cityYields' => $cityYields,
            'settlersWaiting' => $player->settlers > 0,
            'bestSite' => $bestSite,
            'foundableCount' => count($foundable),
            'researchUnset' => $player->current_tech === null && (bool) $player->availableTechs(),
            'cultureGoal' => Rules::cultureGoal($game),
            'wars' => Economy::warsCount($game, $player),
            'era' => Rules::era(count($player->technologies ?? [])),
            'goldenAge' => $player->inGoldenAge($game->turn) ? $player->golden_age_until - $game->turn + 1 : 0,
            'goldenProgress' => min(100, (int) round(100 * $player->golden_points / Rules::GOLDEN_POINTS_NEEDED)),
            'wonderRaces' => $game->cities->filter(fn ($c) => $c->current_build && Rules::isWonder($c->current_build) && $c->empire_id !== $player->id)
                ->map(fn ($c) => ['empire' => $empiresById->get($c->empire_id), 'wonder' => Economy::buildName($c->current_build), 'pct' => (int) round(100 * $c->production_stored / max(1, Economy::buildCost($c->current_build)))])
                ->sortByDesc('pct')->values(),
            'research' => $research,
            'availableTechs' => $player->availableTechs(),
            'idleCities' => $idleCities,
            'selected' => $selectedInfo,
            'rivals' => $rivals,
            'landShare' => round(100 * ($ownedBy[$player->id] ?? 0) / $landTotal),
            'dominationShare' => (int) (Rules::DOMINATION_SHARE * 100),
            'winner' => $game->winner_empire_id ? $empiresById->get($game->winner_empire_id) : null,
            'chart' => $this->chart($game, $empiresById),
            'charts' => array_filter(['Score' => $this->chart($game, $empiresById, 1), 'Land' => $this->chart($game, $empiresById, 2), 'Culture' => $this->chart($game, $empiresById, 3)]),
            'replay' => $game->isActive() || ! $game->replay ? null : [
                'w' => $game->width,
                'h' => $game->height,
                'land' => $tiles->sortBy(fn ($t) => $t->y * $game->width + $t->x)->map(fn ($t) => $t->isLand() ? ($t->terrain === 'mountain' ? 'm' : 'l') : 'o')->implode(''),
                'colors' => $game->empires->sortBy('id')->values()->map(fn ($e) => $e->color)->all(),
                'names' => $game->empires->sortBy('id')->values()->map(fn ($e) => $e->name)->all(),
                'frames' => $game->replay,
            ],
            'victory' => [
                'domination' => $game->empires->filter(fn ($e) => ! $e->eliminated)->map(fn ($e) => ['empire' => $e, 'pct' => min(100, (int) round(100 * (($ownedBy[$e->id] ?? 0) / $landTotal) / Rules::DOMINATION_SHARE))])->sortByDesc('pct')->values()->take(3),
                'culture' => $game->empires->filter(fn ($e) => ! $e->eliminated)->map(fn ($e) => ['empire' => $e, 'pct' => min(100, (int) round(100 * Economy::culture($e) / Rules::cultureGoal($game)))])->sortByDesc('pct')->values()->take(3),
                'science' => $game->empires->filter(fn ($e) => ! $e->eliminated)->map(function ($e) use ($game) {
                    $techs = count($e->technologies ?? []);
                    $building = $game->cities->first(fn ($c) => $c->empire_id === $e->id && $c->current_build === 'grand_expedition');
                    $launch = $building ? (int) round(100 * $building->production_stored / Rules::BUILDINGS['grand_expedition']['cost']) : 0;

                    // Two halves: knowing every tech, then building the expedition.
                    return ['empire' => $e, 'pct' => min(100, (int) round(50 * $techs / count(Rules::TECHS)) + intdiv($launch, 2)), 'launch' => $building ? $launch : null];
                })->sortByDesc('pct')->values()->take(3),
                'diplomacy' => $game->empires->filter(fn ($e) => ! $e->eliminated)->map(function ($e) use ($game) {
                    $others = $game->empires->filter(fn ($o) => ! $o->eliminated && $o->id !== $e->id);
                    $allies = $others->filter(fn ($o) => $game->allied($e->id, $o->id))->count();

                    return ['empire' => $e, 'allies' => $allies, 'of' => $others->count(), 'pct' => $others->count() ? (int) round(100 * $allies / $others->count()) : 0];
                })->sortByDesc('pct')->values()->take(3),
                'turnsLeft' => max(0, $game->max_turns - $game->turn + 1),
            ],
            'tips' => $tips,
            'chronicle' => array_reverse($game->log ?? []),
            'toasts' => collect($game->events ?? [])->map(fn ($e) => ['text' => $e, 'kind' => Rules::eventKind($e)])->values(),
            'difficulty' => Rules::DIFFICULTIES[$game->difficulty ?? 'prince'],
        ];
    }
}
