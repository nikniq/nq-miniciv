<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Things an empire can do between turns: found a city, choose what a city
 * builds, rush-buy it, choose research. Shared by the controller and the AI,
 * so every rule is enforced in one place.
 */
final class Actions
{
    /**
     * Why a city cannot be founded on $tile, or null if it can.
     *
     * @param  Collection<int, Tile>  $tiles  every tile in the game
     */
    public function foundingProblem(Game $game, Empire $empire, Tile $tile, Collection $tiles): ?string
    {
        if ($empire->settlers < 1) {
            return 'You have no settlers. Build some in a city first.';
        }
        if (! $tile->canSettle()) {
            return 'A city cannot be founded on '.strtolower(Rules::TERRAIN[$tile->terrain]['name']).'.';
        }
        if ($tile->camp) {
            return 'A barbarian camp occupies this tile. Clear it first.';
        }
        if ($tile->empire_id && $tile->empire_id !== $empire->id) {
            return 'That land belongs to another empire.';
        }
        $cityTiles = $tiles->whereIn('id', $game->cities->pluck('tile_id'));
        foreach ($cityTiles as $ct) {
            if ($ct->distanceTo($tile) < Rules::MIN_CITY_DISTANCE) {
                return 'Too close to an existing city (minimum '.Rules::MIN_CITY_DISTANCE.' tiles apart).';
            }
        }

        return null;
    }

    /**
     * Found a city. Claims the surrounding unowned tiles. Returns the city.
     *
     * @param  Collection<int, Tile>  $tiles  every tile in the game (mutated in place)
     */
    public function foundCity(Game $game, Empire $empire, Tile $tile, Collection $tiles, bool $capital = false): City
    {
        if (! $capital) {
            $problem = $this->foundingProblem($game, $empire, $tile, $tiles);
            if ($problem) {
                throw new InvalidArgumentException($problem);
            }
            $empire->settlers -= 1;
            $empire->save();
        }

        $city = City::create([
            'game_id' => $game->id,
            'empire_id' => $empire->id,
            'tile_id' => $tile->id,
            'name' => $this->nextCityName($empire, $game),
            'population' => 1,
            'buildings' => [],
            'culture' => 0,
            'is_capital' => $capital,
            'current_build' => 'monument',
        ]);

        $tile->empire_id = $empire->id;
        $tile->city_id = $city->id;
        $tile->save();

        $this->expandBorders($city, $tile, $tiles, Rules::borderRadiusSq(0));

        $game->cities->push($city);
        $empire->setRelation('cities', $empire->cities->push($city));

        return $city;
    }

    /** Claim unowned tiles within radius² of the city centre. */
    public function expandBorders(City $city, Tile $centre, Collection $tiles, int $radiusSq, ?array $grid = null): int
    {
        if ($grid === null) {
            $grid = [];
            foreach ($tiles as $t) {
                $grid[$t->y][$t->x] = $t;
            }
        }
        $r = (int) ceil(sqrt($radiusSq));
        $claimed = 0;
        for ($dy = -$r; $dy <= $r; $dy++) {
            for ($dx = -$r; $dx <= $r; $dx++) {
                if ($dx * $dx + $dy * $dy > $radiusSq) {
                    continue;
                }
                $t = $grid[$centre->y + $dy][$centre->x + $dx] ?? null;
                if (! $t || $t->empire_id !== null || $t->camp) {
                    continue;
                }
                $t->empire_id = $city->empire_id;
                $t->city_id = $city->id;
                $t->save();
                $claimed++;
            }
        }

        return $claimed;
    }

    public function setBuild(City $city, Empire $empire, string $key, array $wondersBuilt, bool $append = false): void
    {
        $options = Economy::buildOptions($city, $empire, $wondersBuilt);
        if (! isset($options[$key])) {
            throw new InvalidArgumentException('That cannot be built here right now.');
        }
        if ($append && $city->current_build && $city->current_build !== 'wealth') {
            $queue = $city->queue ?? [];
            if (count($queue) >= 6) {
                throw new InvalidArgumentException('The queue is full (6 items).');
            }
            if ($key !== 'settlers' && ! isset(Rules::UNITS[$key]) && (in_array($key, $queue, true) || $key === $city->current_build)) {
                throw new InvalidArgumentException('That is already planned.');
            }
            $queue[] = $key;
            $city->queue = $queue;
        } else {
            $city->current_build = $key;
        }
        $city->save();
    }

    public function removeFromQueue(City $city, int $position): void
    {
        $queue = $city->queue ?? [];
        if (! array_key_exists($position, $queue)) {
            throw new InvalidArgumentException('Nothing at that position.');
        }
        unset($queue[$position]);
        $city->queue = array_values($queue);
        $city->save();
    }

    /** Pay gold to finish the current build immediately. */
    public function buyBuild(City $city, Empire $empire): int
    {
        $key = $city->current_build;
        $cost = $key ? Economy::buildCost($key) : null;
        if ($cost === null) {
            throw new InvalidArgumentException('Nothing purchasable is being built.');
        }
        $remaining = max(0, $cost - $city->production_stored);
        $price = $remaining * Rules::BUY_RATE;
        if ($price === 0) {
            throw new InvalidArgumentException('It will finish this turn anyway.');
        }
        if ($empire->gold < $price) {
            throw new InvalidArgumentException("Not enough gold: that costs {$price}.");
        }
        $empire->gold -= $price;
        $empire->save();
        $city->production_stored = $cost;
        $city->save();

        return $price;
    }

    public function setResearch(Empire $empire, string $tech): void
    {
        if (! isset($empire->availableTechs()[$tech])) {
            throw new InvalidArgumentException('That technology is not available yet.');
        }
        $empire->current_tech = $tech;
        $empire->save();
    }

    /**
     * Why $attacker cannot attack $target, or null if it can. A city can be
     * attacked when its borders touch the attacker's territory.
     *
     * @param  Collection<int, Tile>  $tiles  every tile in the game
     */
    public function attackProblem(Empire $attacker, City $target, Collection $tiles, ?Game $game = null, ?TileIndex $index = null): ?string
    {
        if ($target->empire_id === $attacker->id) {
            return 'That is your own city.';
        }
        if ($game && ! $game->atWar($attacker->id, $target->empire_id)) {
            return 'You are at peace with this empire. Declare war first.';
        }
        if ($attacker->army < 1) {
            return 'You have no army. Build units in a city first.';
        }
        if (! $this->bordersTouch($attacker, $target, $tiles, $index)) {
            return 'Your territory does not border this city.';
        }

        return null;
    }

    public function bordersTouch(Empire $attacker, City $target, Collection $tiles, ?TileIndex $index = null): bool
    {
        return ($index ?? new TileIndex($tiles))->touches($attacker->id, $target->id);
    }

    /** Garrison strength a defender spreads over each of its cities. */
    public function garrison(Empire $defender): int
    {
        return intdiv($defender->army, max(1, $defender->cities->count()));
    }

    /**
     * Resolve an attack. Captures the city and its land on success. Returns
     * ['captured' => bool, 'text' => string, 'eliminated' => ?Empire].
     *
     * @param  Collection<int, Tile>  $tiles  every tile in the game (mutated)
     */
    public function attack(Game $game, Empire $attacker, City $target, Collection $tiles): array
    {
        $problem = $this->attackProblem($attacker, $target, $tiles, $game);
        if ($problem) {
            throw new InvalidArgumentException($problem);
        }

        $defender = $game->empires->firstWhere('id', $target->empire_id);
        $garrison = $this->garrison($defender);
        $defense = Rules::cityDefense($target, $garrison, Economy::empireEffects($defender)['defense'] ?? 0);
        $eliminated = null;

        if ($attacker->army > $defense) {
            $attacker->army -= (int) ceil($defense * 0.8);
            $defender->army = max(0, $defender->army - $garrison);

            $target->empire_id = $attacker->id;
            $target->is_capital = false;
            $target->population = max(1, $target->population - 1);
            $target->current_build = 'warriors';
            $target->production_stored = 0;
            $target->save();
            foreach ($tiles->where('city_id', $target->id) as $t) {
                $t->empire_id = $attacker->id;
                $t->save();
            }

            $defender->setRelation('cities', $defender->cities->reject(fn (City $c) => $c->id === $target->id)->values());
            $attacker->setRelation('cities', $attacker->cities->push($target));
            if ($defender->cities->isEmpty()) {
                $defender->eliminated = true;
                $eliminated = $defender;
            }
            $defender->save();
            $attacker->save();
            $game->adjustAttitude($attacker->id, $defender->id, -30);
            $game->save();

            return ['captured' => true, 'eliminated' => $eliminated,
                'text' => "{$attacker->name} captured {$target->name} from {$defender->name}."];
        }

        $attacker->army -= (int) ceil($attacker->army / 2);
        $defender->army = max(0, $defender->army - min($garrison, intdiv($attacker->army, 3)));
        $attacker->save();
        $defender->save();

        return ['captured' => false, 'eliminated' => null,
            'text' => "{$attacker->name}'s assault on {$target->name} was repelled (defence {$defense})."];
    }

    public function warProblem(Game $game, Empire $a, Empire $b): ?string
    {
        if ($a->id === $b->id) {
            return 'You cannot declare war on yourself.';
        }
        if ($b->eliminated) {
            return 'That empire no longer exists.';
        }
        $rel = $game->relation($a->id, $b->id);
        if ($rel['state'] === 'war') {
            return 'You are already at war.';
        }
        if ($rel['since'] > 0 && $game->turn - $rel['since'] < Rules::PEACE_MIN_TURNS) {
            $left = Rules::PEACE_MIN_TURNS - ($game->turn - $rel['since']);
            return "The peace treaty holds for another {$left} turns.";
        }

        return null;
    }

    public function declareWar(Game $game, Empire $a, Empire $b): void
    {
        $problem = $this->warProblem($game, $a, $b);
        if ($problem) {
            throw new InvalidArgumentException($problem);
        }
        $game->setDiplomacy($a->id, $b->id, 'war');
        $game->adjustAttitude($a->id, $b->id, -40);
        // Third parties dislike warmongers a little.
        foreach ($game->empires as $other) {
            if ($other->id !== $a->id && $other->id !== $b->id && ! $other->eliminated) {
                $game->adjustAttitude($a->id, $other->id, -5);
            }
        }
        $game->save();
    }

    /** Give gold to improve relations: +1 attitude per 10 gold, up to +30 per gift. */
    public function gift(Game $game, Empire $from, Empire $to, int $gold): int
    {
        if ($to->eliminated || $from->id === $to->id) {
            throw new InvalidArgumentException('There is no one to receive that.');
        }
        if ($gold < 10 || $from->gold < $gold) {
            throw new InvalidArgumentException('A gift needs at least 10 gold, and you must have it.');
        }
        $from->gold -= $gold;
        $from->save();
        $to->gold += $gold;
        $to->save();
        $delta = min(30, intdiv($gold, 10));
        $game->adjustAttitude($from->id, $to->id, $delta);
        $game->save();

        return $delta;
    }

    /**
     * Demand tribute. Works when the target is clearly weaker and not yet
     * hostile; otherwise they refuse and resent it. Returns gold received.
     */
    public function demandTribute(Game $game, Empire $from, Empire $to): array
    {
        if ($to->eliminated || $from->id === $to->id) {
            throw new InvalidArgumentException('There is no one to demand from.');
        }
        if ($game->atWar($from->id, $to->id)) {
            throw new InvalidArgumentException('You are at war; raid or attack instead.');
        }
        $rel = $game->relation($from->id, $to->id);
        if (($rel['tribute_turn'] ?? -99) > $game->turn - 10) {
            throw new InvalidArgumentException('You demanded tribute recently. Wait a few turns.');
        }
        $diplomacy = $game->diplomacy ?? [];
        $key = min($from->id, $to->id).'-'.max($from->id, $to->id);
        $diplomacy[$key] = ($diplomacy[$key] ?? ['state' => 'peace', 'since' => 0]) + ['tribute_turn' => $game->turn];
        $diplomacy[$key]['tribute_turn'] = $game->turn;
        $game->diplomacy = $diplomacy;

        $intimidated = $from->army >= 2 * $to->army + 5 && $game->attitude($from->id, $to->id) > -40;
        if ($intimidated && $to->gold >= 20) {
            $amount = min($to->gold, 20 + 6 * $to->cities->count());
            $to->gold -= $amount;
            $to->save();
            $from->gold += $amount;
            $from->save();
            $game->adjustAttitude($from->id, $to->id, -15);
            $game->save();

            return ['paid' => true, 'gold' => $amount];
        }
        $game->adjustAttitude($from->id, $to->id, -10);
        $game->save();

        return ['paid' => false, 'gold' => 0];
    }

    public function raidProblem(Game $game, Empire $attacker, City $target, Collection $tiles, ?TileIndex $index = null): ?string
    {
        if ($target->empire_id === $attacker->id) {
            return 'That is your own city.';
        }
        if (! $game->atWar($attacker->id, $target->empire_id)) {
            return 'You are at peace with this empire. Declare war first.';
        }
        if ($attacker->army < Rules::RAID_MIN_ARMY) {
            return 'A raid needs an army of at least '.Rules::RAID_MIN_ARMY.'.';
        }
        if (! $this->bordersTouch($attacker, $target, $tiles, $index)) {
            return 'Your territory does not border this city.';
        }

        return null;
    }

    /** Pillage a bordering enemy city: steal gold, burn its food stores. */
    public function raid(Game $game, Empire $attacker, City $target, Collection $tiles): array
    {
        $problem = $this->raidProblem($game, $attacker, $target, $tiles);
        if ($problem) {
            throw new InvalidArgumentException($problem);
        }
        $defender = $game->empires->firstWhere('id', $target->empire_id);
        $gold = min($defender->gold, 10 + 3 * $target->population);
        $defender->gold -= $gold;
        $defender->save();
        $attacker->gold += $gold;
        $attacker->army = max(0, $attacker->army - 1);
        $attacker->save();
        $target->food_stored = 0;
        $target->production_stored = (int) floor($target->production_stored / 2);
        $target->save();
        $game->adjustAttitude($attacker->id, $defender->id, -10);
        $game->save();

        return ['gold' => $gold, 'text' => "{$attacker->name} raided {$target->name}: {$gold} gold looted, granaries burned."];
    }

    /** Whether the AI empire $b would accept peace from $a right now. */
    public function peaceAcceptable(Game $game, Empire $a, Empire $b): bool
    {
        $rel = $game->relation($a->id, $b->id);
        if ($rel['state'] !== 'war') {
            return false;
        }
        $duration = $game->turn - $rel['since'];
        $attitude = $game->attitude($a->id, $b->id);

        // Tired of a long war, clearly losing it, or never that angry.
        return $duration >= 12 || $b->army < $a->army * 0.6 || ($duration >= 5 && $attitude > -30);
    }

    public function proposePeace(Game $game, Empire $a, Empire $b): bool
    {
        if (! $game->atWar($a->id, $b->id)) {
            throw new InvalidArgumentException('You are not at war with that empire.');
        }
        if (! $this->peaceAcceptable($game, $a, $b)) {
            return false;
        }
        $game->setDiplomacy($a->id, $b->id, 'peace');
        $game->save();

        return true;
    }

    public function campProblem(Empire $empire, Tile $tile, Collection $tiles): ?string
    {
        if (! $tile->camp) {
            return 'There is no camp here.';
        }
        if ($empire->army < Rules::CAMP_CLEAR_ARMY) {
            return 'You need an army of at least '.Rules::CAMP_CLEAR_ARMY.' to clear a camp.';
        }
        $near = $tiles->first(fn (Tile $t) => $t->empire_id === $empire->id && $t->distanceTo($tile) <= 2);
        if (! $near) {
            return 'The camp is too far from your territory (within 2 tiles).';
        }

        return null;
    }

    /** Clear a barbarian camp. Returns the gold looted. */
    public function clearCamp(Empire $empire, Tile $tile, Collection $tiles): int
    {
        $problem = $this->campProblem($empire, $tile, $tiles);
        if ($problem) {
            throw new InvalidArgumentException($problem);
        }
        $empire->army -= Rules::CAMP_CLEAR_LOSS;
        $loot = 20 + 5 * (($tile->x + $tile->y) % 7);
        $empire->gold += $loot;
        $empire->save();
        $tile->camp = false;
        $tile->save();

        return $loot;
    }

    public function setFocus(City $city, string $focus): void
    {
        if (! isset(Rules::FOCUS[$focus])) {
            throw new InvalidArgumentException('Unknown focus.');
        }
        $city->focus = $focus;
        $city->save();
    }

    /**
     * Best legal city site within reach (6 tiles) of the empire's cities:
     * most food, production and gold in the surrounding ring, unowned and
     * properly spaced. Shared by the AI and the "found at best site" button.
     *
     * @param  Collection<int, Tile>  $tiles
     */
    public function bestSite(Game $game, Empire $empire, Collection $tiles, int $reach = 6): ?Tile
    {
        $own = $tiles->whereIn('id', $empire->cities->pluck('tile_id'));
        if ($own->isEmpty()) {
            return null;
        }
        $grid = [];
        foreach ($tiles as $t) {
            $grid[$t->y][$t->x] = $t;
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
            if ($near > $reach || $near < Rules::MIN_CITY_DISTANCE) {
                continue;
            }
            foreach ($allCentres as $c) {
                if ($c->distanceTo($t) < Rules::MIN_CITY_DISTANCE) {
                    continue 2;
                }
            }
            $score = $t->river ? 3 : 0;
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    $n = $grid[$t->y + $dy][$t->x + $dx] ?? null;
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

    private function nextCityName(Empire $empire, Game $game): string
    {
        $pool = Rules::CITY_NAMES[$empire->name] ?? null;
        $used = $game->cities->pluck('name')->all();
        if ($pool) {
            foreach ($pool as $n) {
                if (! in_array($n, $used, true)) {
                    return $n;
                }
            }
        }
        $n = $empire->cities->count() + 1;

        return $empire->name.' '.['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'][min($n, 10) - 1];
    }
}
