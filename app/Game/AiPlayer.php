<?php

namespace App\Game;

use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * A simple rival: expands while there is room, then builds up. Uses the
 * same Actions as the player so it cannot cheat.
 */
final class AiPlayer
{
    public function __construct(private Actions $actions) {}

    /** @param Collection<int, Tile> $tiles */
    public function act(Game $game, Empire $empire, Collection $tiles, array $wondersBuilt): void
    {
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

        $wantCities = min(6, 2 + intdiv($game->turn, 20));
        $roomToGrow = $this->bestSite($game, $empire, $tiles) !== null;

        foreach ($empire->cities as $city) {
            $options = Economy::buildOptions($city, $empire, $wondersBuilt);
            $current = $city->current_build;

            // Keep what is in progress unless it is wealth or now impossible.
            if ($current && $current !== 'wealth' && isset($options[$current])) {
                continue;
            }

            if ($roomToGrow && $empire->cities->count() < $wantCities && $city->population >= 2 && $empire->settlers === 0) {
                $city->current_build = 'settlers';
                $city->save();
                $roomToGrow = false; // one settler at a time

                continue;
            }

            unset($options['settlers'], $options['wealth']);
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

        // Spend a gold hoard on the capital's build.
        if ($empire->gold > 150) {
            $capital = $empire->cities->firstWhere('is_capital', true) ?? $empire->cities->first();
            if ($capital && $capital->current_build && $capital->current_build !== 'wealth') {
                try {
                    $this->actions->buyBuild($capital, $empire);
                } catch (InvalidArgumentException) {
                    // fine, keep saving
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
        uasort($available, fn ($a, $b) => $a['cost'] <=> $b['cost']);

        return array_key_first($available);
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

        $best = null;
        $bestScore = -1;
        foreach ($tiles as $t) {
            if (! $t->canSettle() || $t->empire_id) {
                continue;
            }
            $near = $own->min(fn (Tile $c) => $c->distanceTo($t));
            if ($near > 6 || $near < Rules::MIN_CITY_DISTANCE) {
                continue;
            }
            if ($this->actions->foundingProblem($game, $empire, $t, $tiles) !== null && $empire->settlers > 0) {
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
