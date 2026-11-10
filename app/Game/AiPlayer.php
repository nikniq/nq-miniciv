<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * A simple rival: expands while there is room, builds up, arms itself when
 * a neighbour looks weak or threatening, and attacks when the odds are
 * clearly in its favour. Uses the same Actions as the player so it cannot
 * cheat.
 */
final class AiPlayer
{
    public function __construct(private Actions $actions) {}

    /**
     * @param  Collection<int, Tile>  $tiles
     * @return string[] events worth telling the player about
     */
    public function act(Game $game, Empire $empire, Collection $tiles, array $wondersBuilt): array
    {
        $events = [];
        $index = new TileIndex($tiles);

        // Use settlers as soon as there is somewhere to put them.
        while ($empire->settlers > 0) {
            $site = $this->bestSite($game, $empire, $tiles);
            if (! $site) {
                break;
            }
            try {
                $this->actions->foundCity($game, $empire, $site, $tiles);
            } catch (InvalidArgumentException) {
                break;
            }
        }

        // Diplomacy and war. Declare first; attack from the following turn, so
        // the victim gets one turn of warning. Make peace when a war has gone
        // on long enough or is going badly.
        foreach ($game->empires as $other) {
            if ($other->id === $empire->id || $other->eliminated || ! $game->atWar($empire->id, $other->id)) {
                continue;
            }
            if ($this->actions->peaceAcceptable($game, $empire, $other) && ($other->is_ai || $empire->army < $other->army)) {
                $game->setDiplomacy($empire->id, $other->id, 'peace');
                $game->save();
                if (! $other->is_ai) {
                    $events[] = "{$empire->name} has sued for peace. The war is over.";
                }
            }
        }
        if ($game->turn >= 30 && ! Economy::happiness($empire, Economy::warsCount($game, $empire))['unhappy']) {
            foreach ($this->borderingEnemyCities($game, $empire, $index) as [$city, $defense]) {
                if ($empire->army <= $defense * 1.8 || $empire->army < 12) {
                    continue;
                }
                $victim = $game->empires->firstWhere('id', $city->empire_id);
                if (! $game->atWar($empire->id, $victim->id)) {
                    if ($this->actions->warProblem($game, $empire, $victim) === null && Economy::warsCount($game, $empire) === 0) {
                        $this->actions->declareWar($game, $empire, $victim);
                        $events[] = $victim->is_ai
                            ? "{$empire->name} has declared war on {$victim->name}."
                            : "⚔ {$empire->name} has declared war on you! Their army is {$empire->army}.";
                    }
                    break;
                }
                if ($game->turn - $game->relation($empire->id, $victim->id)['since'] < 1) {
                    break;
                }
                $result = $this->actions->attack($game, $empire, $city, $tiles);
                $events[] = $result['text'];
                if ($result['eliminated']) {
                    $events[] = "{$result['eliminated']->name} has been wiped from the map.";
                }
                break; // one attack per turn
            }
        }

        // Clear barbarian camps near home whenever the army can spare it.
        foreach ($tiles->where('camp', true) as $camp) {
            if ($empire->army < Rules::CAMP_CLEAR_ARMY + 1) {
                break;
            }
            if ($this->actions->campProblem($empire, $camp, $tiles) === null) {
                $this->actions->clearCamp($empire, $camp, $tiles);
            }
        }

        $threat = $this->threatLevel($game, $empire, $index);
        $wantArmy = min(24, 2 + intdiv($game->turn, 12) + $threat);
        // An empty treasury means units would only be dismissed again.
        $broke = $empire->gold < 10;
        $wantCities = min(Rules::aiCityCap($game), 2 + intdiv($game->turn, 20));
        $roomToGrow = $this->bestSite($game, $empire, $tiles) !== null;
        $happiness = Economy::happiness($empire, Economy::warsCount($game, $empire));
        $pillaged = $tiles->contains(fn (Tile $t) => $t->camp && $this->actions->campProblem($empire, $t, $tiles) === null);

        foreach ($empire->cities as $city) {
            $options = Economy::buildOptions($city, $empire, $wondersBuilt);
            $current = $city->current_build;

            // Keep what is in progress unless it is wealth or now impossible.
            if ($current && $current !== 'wealth' && isset($options[$current])) {
                continue;
            }

            if ($happiness['unhappy']) {
                foreach (['colosseum', 'courthouse', 'temple', 'monument'] as $fix) {
                    if (isset($options[$fix])) {
                        $city->current_build = $fix;
                        $city->save();

                        continue 2;
                    }
                }
            }

            if ($roomToGrow && $empire->cities->count() < $wantCities && $city->population >= 2 && $empire->settlers === 0) {
                $city->current_build = 'settlers';
                $city->save();
                $roomToGrow = false; // one settler at a time

                continue;
            }

            if ($broke && isset($options['market'])) {
                $city->current_build = 'market';
                $city->save();

                continue;
            }

            if (($empire->army < $wantArmy || ($pillaged && $empire->army < Rules::CAMP_CLEAR_ARMY + 4)) && ! $broke) {
                $units = array_filter($options, fn ($o) => $o['kind'] === 'unit');
                if ($units) {
                    uasort($units, fn ($a, $b) => $b['cost'] <=> $a['cost']); // best unit available
                    $city->current_build = array_key_first($units);
                    $city->save();

                    continue;
                }
            }

            if ($threat > 0 && isset($options['walls'])) {
                $city->current_build = 'walls';
                $city->save();

                continue;
            }

            // Improvements that pay off on this city's land come first.
            $terrainCount = [];
            foreach ($index->cityTiles($city->id) as $ct) {
                $terrainCount[$ct->terrain] = ($terrainCount[$ct->terrain] ?? 0) + 1;
            }
            foreach (['farms' => ['grassland', 'plains'], 'lumber_mill' => ['forest'], 'mine' => ['hills'], 'harbor' => ['ocean']] as $imp => $terrains) {
                $n = array_sum(array_map(fn ($t) => $terrainCount[$t] ?? 0, $terrains));
                if ($n >= 3 && isset($options[$imp])) {
                    $city->current_build = $imp;
                    $city->save();

                    continue 2;
                }
            }

            $options = array_filter($options, fn ($o) => in_array($o['kind'], ['building', 'wonder'], true));
            // Wonders only for larger cities; otherwise cheapest building.
            if ($city->population < 4) {
                $options = array_filter($options, fn ($o) => $o['kind'] !== 'wonder');
            }
            if ($options) {
                uasort($options, fn ($a, $b) => $a['cost'] <=> $b['cost']);
                $city->current_build = array_key_first($options);
            } else {
                $city->current_build = 'wealth';
            }
            $city->save();
        }

        // Spend gold beyond a reserve on whatever cities are building.
        foreach ($empire->cities->sortByDesc('population') as $city) {
            if ($empire->gold < 120) {
                break;
            }
            if ($city->current_build && $city->current_build !== 'wealth') {
                try {
                    $this->actions->buyBuild($city, $empire);
                } catch (InvalidArgumentException) {
                    // too dear or finishing anyway; keep saving
                }
            }
        }

        return $events;
    }

    public function chooseTech(Empire $empire): ?string
    {
        $available = $empire->availableTechs();
        if (! $available) {
            return null;
        }
        // Unhappy empires chase happiness buildings; otherwise prefer techs
        // that unlock something buildable, then the cheapest.
        $prefer = [];
        if ($empire->relationLoaded('cities') && Economy::happiness($empire)['unhappy']) {
            $prefer = ['construction', 'code_of_laws', 'philosophy'];
        }
        foreach ($prefer as $p) {
            if (isset($available[$p])) {
                return $p;
            }
        }
        $unlocking = array_filter($available, function ($t, $key) {
            foreach (Rules::BUILDINGS as $b) {
                if ($b['tech'] === $key && ! $b['wonder']) {
                    return true;
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);
        $pool = $unlocking ?: $available;
        uasort($pool, fn ($a, $b) => $a['cost'] <=> $b['cost']);

        return array_key_first($pool);
    }

    /** @return array<int, array{0: City, 1: int}> enemy cities touching our borders, weakest first */
    private function borderingEnemyCities(Game $game, Empire $empire, TileIndex $index): array
    {
        $out = [];
        foreach ($game->empires as $other) {
            if ($other->id === $empire->id || $other->eliminated) {
                continue;
            }
            $garrison = $this->actions->garrison($other);
            $extra = Economy::empireEffects($other)['defense'] ?? 0;
            foreach ($other->cities as $city) {
                if ($index->touches($empire->id, $city->id)) {
                    $out[] = [$city, Rules::cityDefense($city, $garrison, $extra)];
                }
            }
        }
        usort($out, fn ($a, $b) => $a[1] <=> $b[1]);

        return $out;
    }

    /** How much stronger bordering neighbours are than us, in army points. */
    private function threatLevel(Game $game, Empire $empire, TileIndex $index): int
    {
        $threat = 0;
        foreach ($game->empires as $other) {
            if ($other->id === $empire->id || $other->eliminated) {
                continue;
            }
            foreach ($empire->cities as $city) {
                if ($index->touches($other->id, $city->id)) {
                    $threat = max($threat, $other->army - $empire->army);
                    break;
                }
            }
        }

        return max(0, min(6, intdiv($threat, 3)));
    }

    /** Best legal city site within reach of the empire's existing cities. */
    private function bestSite(Game $game, Empire $empire, Collection $tiles): ?Tile
    {
        $own = $tiles->whereIn('id', $empire->cities->pluck('tile_id'));
        if ($own->isEmpty()) {
            return null;
        }
        $grid = [];
        foreach ($tiles as $t) {
            $grid[$t->x.','.$t->y] = $t;
        }
        $allCentres = $tiles->whereIn('id', $game->cities->pluck('tile_id'))->values();

        $best = null;
        $bestScore = -1;
        foreach ($tiles as $t) {
            if (! $t->canSettle() || $t->empire_id || $t->camp) {
                continue;
            }
            $near = PHP_INT_MAX;
            foreach ($own as $c) {
                $near = min($near, $c->distanceTo($t));
            }
            if ($near > 6 || $near < Rules::MIN_CITY_DISTANCE) {
                continue;
            }
            $tooClose = false;
            foreach ($allCentres as $c) {
                if ($c->distanceTo($t) < Rules::MIN_CITY_DISTANCE) {
                    $tooClose = true;
                    break;
                }
            }
            if ($tooClose) {
                continue;
            }
            $score = 0;
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    $n = $grid[($t->x + $dx).','.($t->y + $dy)] ?? null;
                    if ($n && ! $n->empire_id) {
                        $y = Rules::tileYield($n, $empire);
                        $score += $y['food'] * 2 + $y['prod'] + $y['gold'];
                    }
                }
            }
            $score -= $near; // prefer nearer sites slightly
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $t;
            }
        }

        return $best;
    }
}
