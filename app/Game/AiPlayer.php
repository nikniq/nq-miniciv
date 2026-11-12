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
        $mind = Rules::personality($empire->name);

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
                if ($empire->army <= $defense * $mind['aggression'] || $empire->army < 12) {
                    continue;
                }
                $victim = $game->empires->firstWhere('id', $city->empire_id);
                if (! $game->atWar($empire->id, $victim->id)) {
                    // Friends are not attacked; the bolder the personality, the less friendship it takes.
                    $friendship = $game->attitude($empire->id, $victim->id);
                    if ($friendship > (int) round(10 * $mind['aggression']) || $game->allied($empire->id, $victim->id)) {
                        continue;
                    }
                    if ($this->actions->warProblem($game, $empire, $victim) === null && Economy::warsCount($game, $empire) === 0) {
                        $this->actions->declareWar($game, $empire, $victim);
                        $events[] = $victim->is_ai
                            ? "{$empire->name} has declared war on {$victim->name}."
                            : "⚔ {$empire->name} has declared war on you! Their army is {$empire->army}.";
                        foreach ($this->actions->lastAlliesJoined as $ally) {
                            $events[] = $ally->is_ai
                                ? "🤝 {$ally->name} honours its alliance with {$victim->name} and declares war on {$empire->name}."
                                : "🤝 Your alliance with {$victim->name} draws you into war with {$empire->name}.";
                        }
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

        // At war but outmatched: raid instead of besieging.
        foreach ($this->borderingEnemyCities($game, $empire, $index) as [$city, $defense]) {
            if ($game->atWar($empire->id, $city->empire_id) && $empire->army >= Rules::RAID_MIN_ARMY + 2 && $empire->army <= $defense * $mind['aggression']) {
                $victim = $game->empires->firstWhere('id', $city->empire_id);
                $r = $this->actions->raid($game, $empire, $city, $tiles);
                if (! $victim->is_ai) {
                    $events[] = $r['text'];
                }
                break;
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

        // Government: adopt something better than despotism when it unlocks.
        if (! $empire->inAnarchy($game->turn)) {
            $avail = Economy::availableGovernments($empire);
            $want = null;
            $unhappy = Economy::happiness($empire, Economy::warsCount($game, $empire))['unhappy'];
            $prefs = match ($mind['trait']) {
                'Cultured' => ['theocracy', 'republic', 'monarchy'],
                'Merchant' => ['merchant_republic', 'republic', 'monarchy'],
                'Conqueror' => ['monarchy', 'theocracy'],
                'Scholar' => ['republic', 'merchant_republic', 'monarchy'],
                default => $unhappy ? ['monarchy', 'theocracy', 'republic'] : ['republic', 'monarchy', 'merchant_republic'],
            };
            $rank = array_flip(array_keys(Rules::GOVERNMENTS));
            foreach ($prefs as $g) {
                if (isset($avail[$g]) && $rank[$g] > $rank[$empire->government ?? 'despotism']) {
                    $want = $g;
                    break;
                }
            }
            if ($want && $game->turn >= 10) {
                $this->actions->setGovernment($game, $empire, $want);
            }
        }

        $threat = $this->threatLevel($game, $empire, $index);
        $wantArmy = max(0, min(28, 2 + intdiv($game->turn, 12) + $threat + $mind['army']));
        // An empty treasury means units would only be dismissed again.
        $broke = $empire->gold < 10;
        $wantCities = min(Rules::aiCityCap($game) + $mind['expansion'], 2 + $mind['expansion'] + intdiv($game->turn, 20));
        $roomToGrow = $this->bestSite($game, $empire, $tiles) !== null;
        $happiness = Economy::happiness($empire, Economy::warsCount($game, $empire));
        $pillaged = $tiles->contains(fn (Tile $t) => $t->camp && $this->actions->campProblem($empire, $t, $tiles) === null);

        // Once Exploration is known, the most productive city launches the
        // Grand Expedition and keeps at it.
        $expeditionCity = null;
        if ($empire->knows('exploration') && ! in_array('grand_expedition', $wondersBuilt, true)
            && ! $empire->cities->contains(fn ($c) => $c->current_build === 'grand_expedition')) {
            $expeditionCity = $empire->cities->sortByDesc('population')->first();
        }

        foreach ($empire->cities as $city) {
            $options = Economy::buildOptions($city, $empire, $wondersBuilt);
            $current = $city->current_build;

            if ($expeditionCity && $city->is($expeditionCity) && isset($options['grand_expedition'])) {
                $city->current_build = 'grand_expedition';
                $city->save();

                continue;
            }

            // Keep what is in progress unless it is wealth or now impossible.
            if ($current && $current !== 'wealth' && isset($options[$current])) {
                continue;
            }

            if ($happiness['unhappy']) {
                foreach (['cathedral', 'colosseum', 'courthouse', 'guild_hall', 'temple', 'monument'] as $fix) {
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
            if ($city->population < $mind['wonder_pop']) {
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

        // One new caravan a turn when the treasury allows.
        if ($empire->knows('currency') && $empire->gold >= 80) {
            $idle = $empire->cities->first(fn ($c) => ! $c->trade_to);
            if ($idle && ($best = $this->actions->routeOptions($game, $empire, $idle, $tiles, 1))) {
                try {
                    $this->actions->setTradeRoute($game, $empire, $idle, $best[0]['city'], $tiles);
                } catch (InvalidArgumentException) {
                    // no longer possible; try again next turn
                }
            }
        }

        // Modernise the army when there is gold to spare.
        foreach ($empire->upgrades() as $from => $up) {
            if ($empire->gold - $up['gold'] >= 120) {
                $this->actions->upgradeUnits($empire, $from);
            }
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

    /**
     * A war band is coming: hurry the best unit in each threatened city.
     * Called before the empire's cities produce, so a purchase lands in time.
     */
    public function respondToRaids(Game $game, Empire $empire): void
    {
        foreach ($game->raids ?? [] as $raid) {
            $city = $empire->cities->firstWhere('id', $raid['city']);
            if (! $city) {
                continue;
            }
            $defense = Rules::cityDefense($city, $this->actions->garrison($empire), Economy::empireEffects($empire)['defense'] ?? 0);
            if ($defense >= $raid['strength']) {
                continue;
            }
            $units = array_filter(Economy::buildOptions($city, $empire, []), fn ($o) => $o['kind'] === 'unit');
            if ($units) {
                uasort($units, fn ($a, $b) => $b['cost'] <=> $a['cost']);
                $city->current_build = array_key_first($units);
                $city->save();
                try {
                    $this->actions->buyBuild($city, $empire);
                } catch (InvalidArgumentException) {
                    // cannot afford it; the unit is at least in production
                }
            }
        }
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
            $prefer = ['construction', 'code_of_laws', 'philosophy', 'theology'];
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
        return $this->actions->bestSite($game, $empire, $tiles);
    }
}
