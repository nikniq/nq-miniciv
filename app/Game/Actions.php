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
    public function expandBorders(City $city, Tile $centre, Collection $tiles, int $radiusSq): int
    {
        $claimed = 0;
        foreach ($tiles as $t) {
            if ($t->empire_id !== null) {
                continue;
            }
            $d = ($t->x - $centre->x) ** 2 + ($t->y - $centre->y) ** 2;
            if ($d <= $radiusSq) {
                $t->empire_id = $city->empire_id;
                $t->city_id = $city->id;
                $t->save();
                $claimed++;
            }
        }

        return $claimed;
    }

    public function setBuild(City $city, Empire $empire, string $key, array $wondersBuilt): void
    {
        $options = Economy::buildOptions($city, $empire, $wondersBuilt);
        if (! isset($options[$key])) {
            throw new InvalidArgumentException('That cannot be built here right now.');
        }
        $city->current_build = $key;
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
