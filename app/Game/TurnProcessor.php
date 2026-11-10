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

    public function __construct(private Actions $actions, private AiPlayer $ai) {}

    public function endTurn(Game $game): Game
    {
        return DB::transaction(function () use ($game) {
            $game->load(['empires.cities', 'cities', 'tiles']);
            $this->tiles = $game->tiles;
            $this->events = [];
            $player = $game->playerEmpire();

            $wondersBuilt = $this->wondersBuilt($game);

            foreach ($game->empires as $empire) {
                $effects = Economy::empireEffects($empire);
                $science = 0;
                $gold = 0;

                foreach ($empire->cities as $city) {
                    $cityTiles = $this->tiles->where('city_id', $city->id);
                    $y = Economy::cityYields($city, $empire, $cityTiles, $effects);
                    $science += $y['science'];
                    $gold += $y['gold'];

                    $this->growth($city, $y, $empire);
                    $gold += $this->production($game, $city, $empire, $y, $wondersBuilt);
                    $this->culture($city, $y, $empire);
                    $city->save();
                }

                $empire->gold += $gold;
                $this->research($empire, $science);

                if ($empire->is_ai) {
                    $this->ai->act($game, $empire, $this->tiles, $wondersBuilt);
                }

                $empire->save();
            }

            $game->turn += 1;
            $this->scoresAndVictory($game, $player);

            $game->events = array_slice($this->events, 0, 12);
            $game->save();

            return $game;
        });
    }

    private function growth(City $city, array $y, Empire $empire): void
    {
        $city->food_stored += $y['surplus'];
        if ($city->food_stored >= $city->foodToGrow()) {
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

        $city->production_stored += $y['prod'];
        $cost = Economy::buildCost($key);
        if ($cost === null || $city->production_stored < $cost) {
            return 0;
        }

        if ($key === 'settlers') {
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
        $city->current_build = $this->nextBuild($city, $empire, $wondersBuilt);

        return 0;
    }

    private function nextBuild(City $city, Empire $empire, array $wondersBuilt): string
    {
        $options = Economy::buildOptions($city, $empire, $wondersBuilt);
        unset($options['settlers'], $options['wealth']);
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
            $claimed = $this->actions->expandBorders($city, $centre, $this->tiles, $after);
            if ($claimed) {
                $this->note($empire, "The borders of {$city->name} have expanded.");
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

        $best = null;
        foreach ($game->empires as $empire) {
            $owned = $ownedBy[$empire->id] ?? 0;
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
        }

        if ($game->turn > $game->max_turns && $best) {
            $game->status = $best->is_ai ? 'ended' : 'won';
            $game->winner_empire_id = $best->id;
            array_unshift($this->events, $best->is_ai
                ? "Time is up. {$best->name} finishes with the greatest empire."
                : 'Time is up. Your empire stands above all others. Victory!');
        }
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
