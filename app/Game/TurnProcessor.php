<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Advances the game one turn: every city grows and builds, every empire
 * researches and expands, AI empires act, then victory is checked.
 */
final class TurnProcessor
{
    /** @var string[] */
    private array $events = [];

    /** @var Collection<int, Tile> */
    private Collection $tiles;

    /** @var array<int, array<int, Tile>> y => x => tile */
    private array $grid = [];

    public function __construct(private Actions $actions, private AiPlayer $ai) {}

    public function endTurn(Game $game): Game
    {
        return DB::transaction(function () use ($game) {
            $game->load(['empires.cities', 'cities', 'tiles']);
            $this->tiles = $game->tiles;
            $this->events = [];
            $this->grid = [];
            foreach ($this->tiles as $t) {
                $this->grid[$t->y][$t->x] = $t;
            }
            $player = $game->playerEmpire();

            $wondersBuilt = $this->wondersBuilt($game);

            foreach ($game->empires as $empire) {
                if ($empire->eliminated) {
                    continue;
                }
                $effects = Economy::empireEffects($empire);
                $diff = Rules::DIFFICULTIES[$game->difficulty ?? 'prince'];
                $happiness = Economy::happiness($empire, Economy::warsCount($game, $empire), $empire->is_ai ? 0 : $diff['player_happy']);
                $anarchy = $empire->inAnarchy($game->turn);
                if ($anarchy) {
                    $this->note($empire, 'Anarchy: production and science are halved while the new government takes hold.');
                }
                if ($happiness['unhappy']) {
                    $this->note($empire, 'Unrest across your empire: no growth and production is down.'.($happiness['war_weariness'] ? ' War weariness is part of it.' : ' Build monuments and temples.'));
                }
                $science = 0;
                $gold = 0;

                // Golden ages: happiness surplus accrues points; enough points
                // start an age of extra production and gold.
                $golden = $empire->inGoldenAge($game->turn);
                if (! $golden && $happiness['balance'] > 0) {
                    $empire->golden_points += $happiness['balance'];
                    if ($empire->golden_points >= Rules::GOLDEN_POINTS_NEEDED) {
                        $empire->golden_points = 0;
                        $empire->golden_age_until = $game->turn + Rules::GOLDEN_AGE_TURNS;
                        $golden = true;
                        $this->note($empire, '✦ A Golden Age begins! +'.(int) (Rules::GOLDEN_AGE_BONUS * 100).'% production and gold for '.Rules::GOLDEN_AGE_TURNS.' turns.');
                    }
                } elseif ($golden && $empire->golden_age_until === $game->turn) {
                    $this->note($empire, 'The Golden Age draws to a close.');
                }

                // A capital lost to conquest: the largest city takes over.
                if ($empire->cities->isNotEmpty() && ! $empire->cities->contains('is_capital', true)) {
                    $newCap = $empire->cities->sortByDesc('population')->first();
                    $newCap->is_capital = true;
                    $newCap->save();
                    $this->note($empire, "The court moves to {$newCap->name}, your new capital.");
                }

                $index = new TileIndex($this->tiles);
                foreach ($empire->cities as $city) {
                    $cityTiles = collect($index->cityTiles($city->id));
                    $centre = $this->tiles->firstWhere('id', $city->tile_id);
                    $pillaged = $centre ? $this->campsNear($centre) : 0;
                    if ($pillaged) {
                        $this->note($empire, "Barbarians are pillaging around {$city->name} (−".(2 * $pillaged).' food). Clear the camp with your army.');
                    }
                    $y = Economy::cityYields($city, $empire, $cityTiles, $effects, $happiness['unhappy'], $pillaged, $golden);
                    if ($empire->is_ai) {
                        $mult = $diff['ai'] ?? 1.0;
                        $y['prod'] = (int) round($y['prod'] * $mult);
                        $y['science'] = (int) round($y['science'] * $mult);
                    } else {
                        $y['gold'] = (int) round($y['gold'] * $diff['player_gold']);
                    }
                    if ($anarchy) {
                        $y['prod'] = intdiv($y['prod'], 2);
                        $y['science'] = intdiv($y['science'], 2);
                    }
                    $science += $y['science'];
                    $gold += $y['gold'];

                    $this->growth($city, $y, $empire);
                    $gold += $this->production($game, $city, $empire, $y, $wondersBuilt);
                    $this->culture($city, $y, $empire);
                    $city->save();
                }

                $gold += Economy::tradeGold($game, $empire, $this->tiles);
                $this->greatPeople($game, $empire);

                $this->randomEvent($game, $empire);

                $upkeep = Economy::upkeep($empire)['total'];
                $empire->gold += $gold - $upkeep;
                if ($empire->gold < 0) {
                    // Bankrupt: the shortfall is covered by dismissing troops,
                    // then by halting research for the turn.
                    $this->note($empire, 'The treasury is empty. Upkeep is outstripping income.');
                    $empire->loseStrength(2);
                    $empire->gold = 0;
                    $science = intdiv($science, 2);
                }
                $this->research($empire, $science);

                if ($empire->is_ai) {
                    foreach ($this->ai->act($game, $empire, $this->tiles, $wondersBuilt) as $event) {
                        $this->events[] = $event;
                    }
                }

                $empire->save();
            }

            $this->attitudeDrift($game);
            $this->culturalPressure($game);
            $this->spawnCamps($game);
            $game->turn += 1;
            $this->scoresAndVictory($game, $player);
            $this->recordHistory($game);

            foreach (Achievements::check($game) as $a) {
                array_unshift($this->events, "🏅 Achievement unlocked: {$a['name']} — {$a['blurb']}");
            }
            $game->events = array_slice($this->events, 0, 12);
            // Chronicle: the last 8 turns' events, oldest first.
            $log = $game->log ?? [];
            if ($this->events) {
                $log[] = ['turn' => $game->turn - 1, 'events' => array_slice($this->events, 0, 12)];
            }
            $game->log = array_slice($log, -8);
            $game->save();

            return $game;
        });
    }

    private function growth(City $city, array $y, Empire $empire): void
    {
        $cap = Economy::sizeCap($city);
        $city->food_stored += $y['surplus'];
        if ($city->food_stored >= $city->foodToGrow()) {
            if ($city->population >= $cap) {
                $city->food_stored = $city->foodToGrow() - 1;
                if (! $city->has('aqueduct')) {
                    $this->note($empire, "{$city->name} has reached size {$cap} and cannot grow without an Aqueduct.");
                }

                return;
            }
            $city->food_stored -= $city->foodToGrow();
            $city->population += 1;
            $this->note($empire, "{$city->name} has grown to size {$city->population}.");
        } elseif ($city->food_stored < 0) {
            $city->food_stored = 0;
            if ($city->population > 1) {
                $city->population -= 1;
                $this->note($empire, "Famine in {$city->name}: it shrinks to size {$city->population}.");
            }
        }
    }

    /** Returns gold earned from the Wealth project. */
    private function production(Game $game, City $city, Empire $empire, array $y, array &$wondersBuilt): int
    {
        $key = $city->current_build;
        if ($key === null) {
            return 0;
        }
        if ($key === 'wealth') {
            return $y['prod'];
        }

        // A wonder completed elsewhere this turn: switch to wealth.
        if (Rules::isWonder($key) && in_array($key, $wondersBuilt, true)) {
            $this->note($empire, "{$city->name} lost the race for ".Economy::buildName($key).'; its effort goes to wealth.');
            $empire->gold += $city->production_stored;
            $city->production_stored = 0;
            $city->current_build = 'wealth';

            return 0;
        }

        $wasFresh = $city->production_stored === 0;
        $city->production_stored += $y['prod'];
        $cost = Economy::buildCost($key);
        if ($wasFresh && Rules::isWonder($key) && $empire->is_ai) {
            $this->events[] = "{$empire->name} has begun building the ".Economy::buildName($key).'.';
        }
        if ($cost === null || $city->production_stored < $cost) {
            return 0;
        }

        if (isset(Rules::UNITS[$key])) {
            $empire->addUnit($key);
            $this->note($empire, Rules::UNITS[$key]['name']." mustered in {$city->name} and joined the army (strength {$empire->army}).");
        } elseif ($key === 'settlers') {
            if ($city->population < 2) {
                return 0; // wait until the city can spare the people
            }
            $city->population -= 1;
            $empire->settlers += 1;
            $this->note($empire, "Settlers are ready in {$city->name}. Choose a tile and found a city.");
        } else {
            $city->buildings = array_values(array_unique(array_merge($city->buildings ?? [], [$key])));
            if (Rules::isWonder($key)) {
                $wondersBuilt[] = $key;
                $this->events[] = "{$empire->name} completed the ".Economy::buildName($key).' in '.$city->name.'!';
            } else {
                $this->note($empire, "{$city->name} finished a ".Economy::buildName($key).'.');
            }
        }

        $city->production_stored -= $cost;
        $city->current_build = $this->popQueue($city, $empire, $wondersBuilt) ?? $this->nextBuild($city, $empire, $wondersBuilt);

        return 0;
    }

    /** Next still-valid item from the city's queue, dropping stale ones. */
    private function popQueue(City $city, Empire $empire, array $wondersBuilt): ?string
    {
        $queue = $city->queue ?? [];
        $options = Economy::buildOptions($city, $empire, $wondersBuilt);
        while ($queue) {
            $next = array_shift($queue);
            if (isset($options[$next])) {
                $city->queue = array_values($queue);

                return $next;
            }
        }
        $city->queue = [];

        return null;
    }

    private function nextBuild(City $city, Empire $empire, array $wondersBuilt): string
    {
        // Buildings only: units and settlers are deliberate choices, and a
        // city left to itself should improve rather than arm.
        $options = array_filter(
            Economy::buildOptions($city, $empire, $wondersBuilt),
            fn ($o) => $o['kind'] === 'building'
        );
        if ($options) {
            uasort($options, fn ($a, $b) => $a['cost'] <=> $b['cost']);

            return array_key_first($options);
        }

        return 'wealth';
    }

    private function culture(City $city, array $y, Empire $empire): void
    {
        $before = Rules::borderRadiusSq($city->culture);
        $city->culture += $y['culture'];
        $after = Rules::borderRadiusSq($city->culture);
        if ($after > $before) {
            $centre = $this->tiles->firstWhere('id', $city->tile_id);
            $claimed = $this->actions->expandBorders($city, $centre, $this->tiles, $after, $this->grid);
            if ($claimed) {
                $this->note($empire, "The borders of {$city->name} have expanded.");
            }
        }
    }

    /** Buildings yield great-person points; an arrival is announced, and the AI spends it at once. */
    private function greatPeople(Game $game, Empire $empire): void
    {
        $points = [];
        foreach ($empire->cities as $city) {
            foreach ($city->buildings ?? [] as $b) {
                foreach (Rules::GREAT_SOURCES[$b] ?? [] as $kind => $n) {
                    $points[$kind] = ($points[$kind] ?? 0) + $n;
                }
            }
        }
        if (! $points) {
            return;
        }
        $kind = $empire->addGreatPoints($points);
        if ($kind === null) {
            return;
        }
        $spec = Rules::GREAT_PEOPLE[$kind];
        if (! $empire->is_ai) {
            $this->note($empire, "{$spec['icon']} A {$spec['name']} has arrived at your court! {$spec['blurb']}");

            return;
        }
        // AI: engineers go to the city with the dearest unfinished build.
        $city = $empire->cities->filter(fn ($c) => $c->current_build && $c->current_build !== 'wealth')
            ->sortByDesc(fn ($c) => (Economy::buildCost($c->current_build) ?? 0) - $c->production_stored)->first()
            ?? $empire->cities->first();
        try {
            $this->actions->useGreatPerson($game, $empire, $kind, $city);
        } catch (\InvalidArgumentException) {
            // Nothing sensible to spend it on; it waits.
        }
    }

    private function research(Empire $empire, int $science): void
    {
        $empire->research_progress += $science;
        $tech = $empire->current_tech;
        if ($tech === null) {
            // Science is banked until a target is chosen; AI picks its own.
            if ($empire->is_ai) {
                $empire->current_tech = $this->ai->chooseTech($empire);
            }

            return;
        }
        $cost = Rules::TECHS[$tech]['cost'];
        if ($empire->research_progress >= $cost) {
            $empire->research_progress -= $cost;
            $empire->technologies = array_values(array_unique(array_merge($empire->technologies ?? [], [$tech])));
            $this->note($empire, 'You have discovered '.Rules::TECHS[$tech]['name'].': '.Rules::TECHS[$tech]['unlocks'].'.');
            $empire->current_tech = $empire->is_ai ? $this->ai->chooseTech($empire) : null;
        }
    }

    private function scoresAndVictory(Game $game, ?Empire $player): void
    {
        $landTotal = $this->tiles->filter(fn (Tile $t) => $t->isLand())->count();
        $ownedBy = $this->tiles->filter(fn (Tile $t) => $t->isLand() && $t->empire_id)->countBy('empire_id');

        if ($player && $player->eliminated) {
            $game->status = 'lost';
            array_unshift($this->events, 'Your last city has fallen. The empire is no more.');

            return;
        }

        $best = null;
        foreach ($game->empires as $empire) {
            $owned = $ownedBy[$empire->id] ?? 0;
            if ($empire->eliminated) {
                $empire->score = 0;
                $empire->save();

                continue;
            }
            $empire->score = Economy::score($empire, $owned);
            $empire->save();
            if ($best === null || $empire->score > $best->score) {
                $best = $empire;
            }
            if ($owned / max(1, $landTotal) >= Rules::DOMINATION_SHARE) {
                $game->status = $empire->is_ai ? 'lost' : 'won';
                $game->winner_empire_id = $empire->id;
                array_unshift($this->events, $empire->is_ai
                    ? "{$empire->name} now rules most of the known world. Your empire has been eclipsed."
                    : 'Your empire dominates the known world. Victory!');

                return;
            }
            if (Economy::culture($empire) >= Rules::cultureGoal($game)) {
                $game->status = $empire->is_ai ? 'lost' : 'won';
                $game->winner_empire_id = $empire->id;
                array_unshift($this->events, $empire->is_ai
                    ? "The culture of {$empire->name} has eclipsed all others. The age belongs to them."
                    : 'Your culture is the envy of the world. Cultural victory!');

                return;
            }
        }

        if ($game->turn > $game->max_turns && $best) {
            $game->status = $best->is_ai ? 'ended' : 'won';
            $game->winner_empire_id = $best->id;
            array_unshift($this->events, $best->is_ai
                ? "Time is up. {$best->name} finishes with the greatest empire."
                : 'Time is up. Your empire stands above all others. Victory!');
        }
    }

    /**
     * A little fortune and misfortune, seeded so a turn replays identically.
     * Applies to every empire alike.
     */
    private function randomEvent(Game $game, Empire $empire): void
    {
        mt_srand($game->seed * 31 + $game->turn * 7 + $empire->id);
        if (mt_rand(1, 100) > Rules::EVENT_CHANCE || $empire->cities->isEmpty()) {
            return;
        }
        $city = $empire->cities->values()->get(mt_rand(0, $empire->cities->count() - 1));
        switch (mt_rand(1, 6)) {
            case 1:
            case 2:
                $bonus = 8 + 2 * $city->population;
                $city->food_stored += $bonus;
                $city->save();
                $this->note($empire, "A bumper harvest in {$city->name}: +{$bonus} food stored.");
                break;
            case 3:
                $gold = 15 + 5 * mt_rand(1, 6);
                $empire->gold += $gold;
                $this->note($empire, "Prospectors near {$city->name} strike gold: +{$gold} gold.");
                break;
            case 4:
                if ($city->population >= 5 && ! $city->has('aqueduct')) {
                    $city->population -= 1;
                    $city->save();
                    $this->note($empire, "Plague sweeps {$city->name}: it shrinks to size {$city->population}. An Aqueduct would have prevented it.");
                } else {
                    $this->note($empire, "A festival in {$city->name} lifts spirits. (+{$city->population} culture)");
                    $city->culture += $city->population;
                    $city->save();
                }
                break;
            case 5:
                $bonus = 10 + 2 * $city->population;
                $empire->research_progress += $bonus;
                $this->note($empire, "Scholars in {$city->name} make a breakthrough: +{$bonus} science.");
                break;
            default:
                if ($empire->army > 0) {
                    $empire->loseStrength(1);
                    $this->note($empire, "Deserters leave the army near {$city->name} (−1 strength).");
                } else {
                    $city->production_stored += 10;
                    $city->save();
                    $this->note($empire, "Volunteers in {$city->name} speed the current build (+10 production).");
                }
        }
    }

    /** Score per empire, every turn, for the chart. Kept compact: [turn, [id => score]]. */
    private function recordHistory(Game $game): void
    {
        $history = $game->history ?? [];
        $scores = [];
        foreach ($game->empires as $e) {
            $scores[(string) $e->id] = $e->eliminated ? 0 : (int) $e->score;
        }
        $history[] = [$game->turn - 1, $scores];
        $game->history = $history;
    }

    /**
     * Culture spills over borders: a tile owned by a weak city, lying near a
     * much more cultured foreign city, changes allegiance. At most one tile
     * per city per turn, never a city centre or a tile hugging its own city.
     */
    private function culturalPressure(Game $game): void
    {
        $citiesById = $game->cities->keyBy('id');
        $empiresById = $game->empires->keyBy('id');
        $r = Rules::PRESSURE_RANGE;
        foreach ($game->cities as $city) {
            if ($city->culture < 30) {
                continue;
            }
            $centre = $this->tiles->firstWhere('id', $city->tile_id);
            if (! $centre) {
                continue;
            }
            $best = null;
            for ($dy = -$r; $dy <= $r; $dy++) {
                for ($dx = -$r; $dx <= $r; $dx++) {
                    $t = $this->grid[$centre->y + $dy][$centre->x + $dx] ?? null;
                    if (! $t || ! $t->empire_id || $t->empire_id === $city->empire_id || ! $t->city_id) {
                        continue;
                    }
                    $owner = $citiesById->get($t->city_id);
                    if (! $owner || $owner->tile_id === $t->id) {
                        continue;
                    }
                    $ownerCentre = $this->tiles->firstWhere('id', $owner->tile_id);
                    if ($ownerCentre && $ownerCentre->distanceTo($t) <= 1) {
                        continue;
                    }
                    if ($city->culture >= Rules::PRESSURE_RATIO * max(1, $owner->culture)) {
                        $gap = $city->culture - Rules::PRESSURE_RATIO * $owner->culture;
                        if ($best === null || $gap > $best[1]) {
                            $best = [$t, $gap, $owner];
                        }
                    }
                }
            }
            if ($best) {
                [$t, , $owner] = $best;
                $from = $empiresById->get($t->empire_id);
                $t->empire_id = $city->empire_id;
                $t->city_id = $city->id;
                $t->save();
                $to = $empiresById->get($city->empire_id);
                if ($to && ! $to->is_ai) {
                    $this->events[] = "The culture of {$city->name} wins over a tile from {$owner->name}.";
                } elseif ($from && ! $from->is_ai) {
                    $this->events[] = "A tile near {$owner->name} has defected to {$city->name}, swayed by its culture. Build culture there.";
                }
            }
        }
    }

    /** Relations warm slowly in peace and trade, cool in war, and drift toward neutral. */
    private function attitudeDrift(Game $game): void
    {
        $live = $game->empires->filter(fn (Empire $e) => ! $e->eliminated)->values();
        $partners = [];
        foreach ($live as $e) {
            $partners[$e->id] = Economy::tradePartners($game, $e, $this->tiles);
        }
        foreach ($live as $i => $a) {
            foreach ($live as $j => $b) {
                if ($j <= $i) {
                    continue;
                }
                $att = $game->attitude($a->id, $b->id);
                if ($game->atWar($a->id, $b->id)) {
                    $delta = -2;
                } elseif (in_array($b->id, $partners[$a->id], true)) {
                    $delta = 2;
                } else {
                    $delta = $att > 0 ? -1 : ($att < 0 ? 1 : 0); // forgetting
                }
                if ($delta) {
                    $game->adjustAttitude($a->id, $b->id, $delta);
                }
            }
        }
    }

    /** Camps within distance 2, using the grid window instead of a full scan. */
    private function campsNear(Tile $centre): int
    {
        $n = 0;
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $t = $this->grid[$centre->y + $dy][$centre->x + $dx] ?? null;
                if ($t && $t->camp) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /** A new barbarian camp every 12 turns on empty land away from cities. */
    private function spawnCamps(Game $game): void
    {
        if ($game->turn % 12 !== 0 || $game->turn > 100) {
            return;
        }
        $centres = $this->tiles->whereIn('id', $game->cities->pluck('tile_id'));
        $camps = $this->tiles->where('camp', true);
        $candidates = $this->tiles->filter(function (Tile $t) use ($centres, $camps) {
            if (! $t->canSettle() || $t->empire_id || $t->camp) {
                return false;
            }
            foreach ($centres as $c) {
                if ($c->distanceTo($t) < 3) {
                    return false;
                }
            }
            foreach ($camps as $c) {
                if ($c->distanceTo($t) < 3) {
                    return false;
                }
            }

            return true;
        });
        if ($candidates->isEmpty()) {
            return;
        }
        mt_srand($game->seed + $game->turn);
        $pick = $candidates->values()->get(mt_rand(0, $candidates->count() - 1));
        $pick->camp = true;
        $pick->save();
        $this->events[] = 'Barbarians have made camp in the wilds.';
    }

    private function wondersBuilt(Game $game): array
    {
        $built = [];
        foreach ($game->cities as $c) {
            foreach ($c->buildings ?? [] as $b) {
                if (Rules::isWonder($b)) {
                    $built[] = $b;
                }
            }
        }

        return $built;
    }

    /** Only the player's own news goes into the event feed, plus wonders. */
    private function note(Empire $empire, string $text): void
    {
        if (! $empire->is_ai) {
            $this->events[] = $text;
        }
    }
}
