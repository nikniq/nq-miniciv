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
    private array $byId = [];

    private array $grid = [];

    public function __construct(private Actions $actions, private AiPlayer $ai) {}

    public function endTurn(Game $game): Game
    {
        return DB::transaction(function () use ($game) {
            $game->load(['empires.cities', 'cities', 'tiles']);
            $this->tiles = $game->tiles;
            $this->events = [];
            $this->grid = [];
            $this->byId = [];
            foreach ($this->tiles as $t) {
                $this->grid[$t->y][$t->x] = $t;
                $this->byId[$t->id] = $t;
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
                if ($empire->is_ai) {
                    $this->ai->respondToRaids($game, $empire);
                }
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
                    $centre = ($this->byId[$city->tile_id] ?? null);
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
                $gold += $this->tradeRoutes($game, $empire, $science);
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

            $this->alliances($game, $player);
            $this->revealBorders($game, $player);
            $this->attitudeDrift($game);
            $this->aiTechSwaps($game);
            $this->aiAlliances($game);
            $this->culturalPressure($game);
            $this->drawChoice($game, $player);
            if ($player && ! $player->eliminated) {
                foreach (Quests::check($game, $player, $this->tiles) as $e) {
                    $this->events[] = $e;
                }
                if ($line = Quests::maybeIssue($game, $player, $this->tiles)) {
                    $this->events[] = $line;
                }
            }
            $this->spawnCamps($game);
            $this->barbarianRaids($game);
            $game->turn += 1;
            $this->scoresAndVictory($game, $player);
            $this->recordHistory($game);

            // Achievements read this turn's headline (e.g. how a victory was
            // won), so publish the events before checking.
            $game->events = array_slice($this->events, 0, 12);
            $unlocked = [];
            foreach (Achievements::check($game) as $a) {
                $unlocked[] = "🏅 Achievement unlocked: {$a['name']} — {$a['blurb']}";
            }
            // Announcements follow a victory headline, which stays first.
            $headline = $game->isActive() ? [] : array_splice($this->events, 0, 1);
            $this->events = array_merge($headline, $unlocked, $this->events);
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
            $centre = ($this->byId[$city->tile_id] ?? null);
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

    /**
     * Alliances lapse when trust fades and warm slowly while they hold. The
     * player sees through allies' eyes: their land is revealed each turn.
     */
    private function alliances(Game $game, ?Empire $player): void
    {
        $empires = $game->empires->filter(fn ($e) => ! $e->eliminated)->values();
        foreach ($empires as $i => $a) {
            foreach ($empires->slice($i + 1) as $b) {
                if (! $game->allied($a->id, $b->id)) {
                    continue;
                }
                if ($game->attitude($a->id, $b->id) < Rules::ALLIANCE_LAPSE) {
                    $game->setAllied($a->id, $b->id, false);
                    if ($player && in_array($player->id, [$a->id, $b->id], true)) {
                        $other = $a->id === $player->id ? $b : $a;
                        $this->events[] = "🤝 Trust has faded: the alliance with {$other->name} has lapsed.";
                    }

                    continue;
                }
                if ($game->turn % 3 === 0) {
                    $game->adjustAttitude($a->id, $b->id, 1);
                }
                if ($player && $player->explored !== null && in_array($player->id, [$a->id, $b->id], true)) {
                    $ally = $a->id === $player->id ? $b : $a;
                    foreach ($this->tiles as $t) {
                        if ($t->empire_id === $ally->id) {
                            Fog::reveal($game, $player, $t->x, $t->y, 1);
                        }
                    }
                    $player->save();
                }
            }
        }
    }

    /** The player's borders push back the fog a little every turn. */
    private function revealBorders(Game $game, ?Empire $player): void
    {
        if (! $player || $player->explored === null) {
            return;
        }
        $wondersBefore = $this->tiles->filter(fn (Tile $t) => $t->feature && Fog::visible($game, $player, $t))->pluck('feature')->all();
        $new = 0;
        foreach ($this->tiles as $t) {
            if ($t->empire_id === $player->id) {
                $new += Fog::reveal($game, $player, $t->x, $t->y, Rules::FOG_BORDER_RADIUS);
            }
        }
        if ($new === 0) {
            return;
        }
        $player->save();
        foreach ($this->tiles as $t) {
            if ($t->feature && ! in_array($t->feature, $wondersBefore, true) && Fog::visible($game, $player, $t)) {
                $nw = Rules::NATURAL_WONDERS[$t->feature];
                $this->events[] = "{$nw['glyph']} Your explorers discover {$nw['name']}, a natural wonder.";
            }
        }
        if (Fog::percent($game, $player) >= 100) {
            $this->events[] = '🧭 The whole world is mapped.';
        }
    }

    /**
     * Pay out every trade route of an empire; routes whose partner is now at
     * war, hostile or gone are cut. Returns gold; science is added in place.
     */
    private function tradeRoutes(Game $game, Empire $empire, int &$science): int
    {
        $gold = 0;
        $allCities = $game->empires->flatMap->cities->keyBy('id');
        $byId = collect($this->byId);
        foreach ($empire->cities as $city) {
            if (! $city->trade_to) {
                continue;
            }
            $to = $allCities->get($city->trade_to);
            $partner = $to ? $game->empires->firstWhere('id', $to->empire_id) : null;
            $cut = ! $to || ! $partner || $partner->eliminated
                || ($partner->id !== $empire->id && ($game->atWar($empire->id, $partner->id) || $game->attitude($empire->id, $partner->id) <= -40));
            if ($cut) {
                $city->trade_to = null;
                $city->save();
                $this->note($empire, "🐪 The trade route from {$city->name} has been cut.");

                continue;
            }
            $y = Economy::routeYield($city, $to, $byId->get($city->tile_id), $byId->get($to->tile_id));
            $gold += $y['gold'];
            $science += $y['science'];
            if ($y['food']) {
                $city->food_stored += $y['food'];
                $city->save();
            }
            if ($y['partner']) {
                $partner->gold += $y['partner'];
                $partner->save();
                if ($game->turn % 5 === 0) {
                    $game->adjustAttitude($empire->id, $partner->id, 1);
                }
            }
        }

        return $gold;
    }

    /** An unanswered dilemma defaults to its second answer; then maybe draw a new one. */
    private function drawChoice(Game $game, ?Empire $player): void
    {
        if (! $player || $player->eliminated) {
            return;
        }
        if ($game->choice && ($game->choice['turn'] ?? 0) < $game->turn) {
            $desc = Choices::describe($game, $game->choice);
            $this->events[] = "{$desc['icon']} {$desc['title']}: you let the matter rest. ".Choices::resolve($game, $player, 'b');
        }
        $rivals = $game->empires->filter(fn ($e) => $e->is_ai && ! $e->eliminated && ! $game->atWar($player->id, $e->id))->values()->all();
        $choice = Choices::draw($game, $player, $rivals);
        if ($choice) {
            $game->choice = $choice;
            $desc = Choices::describe($game, $choice);
            $this->events[] = "{$desc['icon']} {$desc['title']}: a decision awaits you.";
        }
    }

    /** Rivals who have grown close form alliances of their own, every few turns. */
    private function aiAlliances(Game $game): void
    {
        if ($game->turn % 5 !== 0) {
            return;
        }
        $ais = $game->empires->filter(fn ($e) => $e->is_ai && ! $e->eliminated)->values();
        foreach ($ais as $i => $a) {
            foreach ($ais->slice($i + 1) as $b) {
                if (! $game->allied($a->id, $b->id) && ! $game->atWar($a->id, $b->id) && $game->attitude($a->id, $b->id) >= Rules::ALLIANCE_ATTITUDE + 10) {
                    $game->setAllied($a->id, $b->id, true);
                    $this->events[] = "🤝 {$a->name} and {$b->name} have formed an alliance.";
                }
            }
        }
    }

    /** Friendly rivals swap techs now and then so the player is not the only trader. */
    private function aiTechSwaps(Game $game): void
    {
        if ($game->turn % 8 !== 0) {
            return;
        }
        $ais = $game->empires->filter(fn ($e) => $e->is_ai && ! $e->eliminated)->values();
        foreach ($ais as $i => $a) {
            foreach ($ais->slice($i + 1) as $b) {
                if ($game->atWar($a->id, $b->id) || $game->attitude($a->id, $b->id) <= 15) {
                    continue;
                }
                $aGets = array_intersect_key($a->availableTechs(), array_flip($b->technologies ?? []));
                $bGets = array_intersect_key($b->availableTechs(), array_flip($a->technologies ?? []));
                if (! $aGets || ! $bGets) {
                    continue;
                }
                uasort($aGets, fn ($x, $y) => $x['cost'] <=> $y['cost']);
                uasort($bGets, fn ($x, $y) => $x['cost'] <=> $y['cost']);
                $a->technologies = array_values(array_unique(array_merge($a->technologies ?? [], [array_key_first($aGets)])));
                $b->technologies = array_values(array_unique(array_merge($b->technologies ?? [], [array_key_first($bGets)])));
                $a->save();
                $b->save();
                $game->adjustAttitude($a->id, $b->id, 3);
            }
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

        // Science victory: the Grand Expedition has sailed. Read the empires'
        // own city models: they carry buildings completed this turn.
        foreach ($game->empires->flatMap->cities as $c) {
            if ($c->has('grand_expedition')) {
                $launcher = $game->empires->firstWhere('id', $c->empire_id);
                if ($launcher && ! $launcher->eliminated) {
                    $game->status = $launcher->is_ai ? 'lost' : 'won';
                    $game->winner_empire_id = $launcher->id;
                    array_unshift($this->events, $launcher->is_ai
                        ? "The Grand Expedition of {$launcher->name} sails from {$c->name} into a new age. Their science has won the world."
                        : "Your Grand Expedition sails from {$c->name} into a new age. Science victory!");
                    foreach ($game->empires as $e) {
                        $e->score = $e->eliminated ? 0 : Economy::score($e, $ownedBy[$e->id] ?? 0);
                        $e->save();
                    }

                    return;
                }
            }
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
        $this->recordFrame($game);
    }

    /**
     * One replay frame: a character per tile, '.' unowned, otherwise the
     * owner's slot ('a' for the first empire, 'b' the next...), and uppercase
     * where a city stands. Big maps record every other turn.
     */
    private function recordFrame(Game $game): void
    {
        $every = $game->width * $game->height > 1500 ? 2 : 1;
        $turn = $game->turn - 1;
        if ($turn % $every !== 0 && $game->isActive()) {
            return;
        }
        $slot = [];
        foreach ($game->empires->sortBy('id')->values() as $i => $e) {
            $slot[$e->id] = chr(ord('a') + $i);
        }
        $cityTiles = [];
        foreach ($game->empires as $e) {
            foreach ($e->cities as $c) {
                $cityTiles[$c->tile_id] = true;
            }
        }
        $row = array_fill(0, $game->width * $game->height, '.');
        foreach ($this->tiles as $t) {
            if ($t->empire_id && isset($slot[$t->empire_id])) {
                $ch = $slot[$t->empire_id];
                $row[$t->y * $game->width + $t->x] = isset($cityTiles[$t->id]) ? strtoupper($ch) : $ch;
            }
        }
        $replay = $game->replay ?? [];
        $replay[] = [$turn, implode('', $row)];
        $game->replay = $replay;
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
            $centre = ($this->byId[$city->tile_id] ?? null);
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
                    $ownerCentre = ($this->byId[$owner->tile_id] ?? null);
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

    /**
     * War bands strike the turn after they are seen; then camps may send new
     * ones at the nearest city in reach. Clearing the camp first calls a band
     * off. A city that holds gains its army experience; one that falls is
     * looted, not taken.
     */
    private function barbarianRaids(Game $game): void
    {
        $cities = $game->empires->flatMap->cities->keyBy('id');
        $empires = $game->empires->keyBy('id');
        $byId = collect($this->byId);
        $pending = [];
        foreach ($game->raids ?? [] as $r) {
            $camp = $byId->get($r['camp']);
            $city = $cities->get($r['city']);
            if (! $camp || ! $camp->camp || ! $city) {
                continue; // camp cleared or city gone: the band disperses
            }
            if ($r['arrives'] > $game->turn) {
                $pending[] = $r;

                continue;
            }
            $owner = $empires->get($city->empire_id);
            $garrison = $this->actions->garrison($owner);
            $defense = Rules::cityDefense($city, $garrison, Economy::empireEffects($owner)['defense'] ?? 0);
            if ($defense >= $r['strength']) {
                $owner->gainVeterancy(1);
                $owner->save();
                $this->note($owner, "☠ Barbarians hurled themselves at {$city->name} and broke against its defence ({$defense} against {$r['strength']}).");

                continue;
            }
            $loot = min($owner->gold, 10 + intdiv($game->turn, 2));
            $owner->gold -= $loot;
            $owner->save();
            $city->food_stored = 0;
            $city->production_stored = intdiv($city->production_stored, 2);
            if ($city->population > 1) {
                $city->population -= 1;
            }
            $city->save();
            $this->note($owner, "☠ Barbarians sacked {$city->name}: stores burned, {$loot} gold taken, the city shrinks to size {$city->population}. Defence {$defense} could not hold {$r['strength']}.");
        }

        if ($game->turn >= Rules::RAID_FROM_TURN) {
            mt_srand($game->seed * 7 + $game->turn * 101);
            $busy = array_column($pending, 'camp');
            foreach ($this->tiles as $t) {
                if (! $t->camp || in_array($t->id, $busy, true) || mt_rand(1, 100) > Rules::RAID_CHANCE) {
                    continue;
                }
                $target = null;
                foreach ($cities as $c) {
                    $ct = $byId->get($c->tile_id);
                    $d = $ct ? $ct->distanceTo($t) : 99;
                    if ($d <= Rules::RAID_REACH && ($target === null || $d < $target[0])) {
                        $target = [$d, $c];
                    }
                }
                if (! $target) {
                    continue;
                }
                $strength = Rules::raidStrength($game->turn);
                $pending[] = ['camp' => $t->id, 'city' => $target[1]->id, 'strength' => $strength, 'arrives' => $game->turn + 1];
                $owner = $empires->get($target[1]->empire_id);
                $this->note($owner, "☠ A barbarian war band (strength {$strength}) marches on {$target[1]->name}. It strikes at the end of next turn: strengthen the army, buy walls, or clear the camp.");
            }
        }
        $game->raids = $pending;
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
