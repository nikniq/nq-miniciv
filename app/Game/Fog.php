<?php

namespace App\Game;

use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Support\Collection;

/**
 * Fog of war for the human player. Each empire may carry a map of explored
 * tiles, one character per tile ('1' = seen). A null map means everything is
 * known, which is how rivals and games from before this feature behave.
 */
final class Fog
{
    public static function init(Game $game, Empire $empire): void
    {
        $empire->explored = str_repeat('0', $game->width * $game->height);
    }

    public static function visible(Game $game, Empire $empire, Tile $tile): bool
    {
        $map = $empire->explored;

        return $map === null || ($map[$tile->y * $game->width + $tile->x] ?? '1') === '1';
    }

    /** Reveal a rounded patch around (cx, cy). Returns how many tiles were new. Does not save. */
    public static function reveal(Game $game, Empire $empire, int $cx, int $cy, int $radius): int
    {
        $map = $empire->explored;
        if ($map === null) {
            return 0;
        }
        $new = 0;
        $w = $game->width;
        $h = $game->height;
        $limit = $radius * $radius + 1;
        for ($dy = -$radius; $dy <= $radius; $dy++) {
            $y = $cy + $dy;
            if ($y < 0 || $y >= $h) {
                continue;
            }
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                $x = $cx + $dx;
                if ($x < 0 || $x >= $w || $dx * $dx + $dy * $dy > $limit) {
                    continue;
                }
                $i = $y * $w + $x;
                if ($map[$i] === '0') {
                    $map[$i] = '1';
                    $new++;
                }
            }
        }
        $empire->explored = $map;

        return $new;
    }

    /** How many tiles a reveal would add, without changing anything. */
    public static function wouldReveal(Game $game, Empire $empire, int $cx, int $cy, int $radius): int
    {
        $copy = clone $empire;
        $n = self::reveal($game, $copy, $cx, $cy, $radius);

        return $n;
    }

    /** Explored share, 0–100. Null maps count as fully explored. */
    public static function percent(Game $game, Empire $empire): int
    {
        $map = $empire->explored;
        if ($map === null) {
            return 100;
        }

        return (int) floor(100 * substr_count($map, '1') / max(1, $game->width * $game->height));
    }

    /**
     * Tile ids the empire can see, or null when it sees everything.
     *
     * @param  Collection<int, Tile>  $tiles
     * @return array<int, true>|null
     */
    public static function visibleIds(Game $game, Empire $empire, Collection $tiles): ?array
    {
        if ($empire->explored === null) {
            return null;
        }
        $out = [];
        foreach ($tiles as $t) {
            if (self::visible($game, $empire, $t)) {
                $out[$t->id] = true;
            }
        }

        return $out;
    }
}
