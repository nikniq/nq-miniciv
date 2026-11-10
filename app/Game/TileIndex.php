<?php

namespace App\Game;

use App\Models\Tile;
use Illuminate\Support\Collection;

/**
 * One-pass lookups over the tile collection: owner by coordinate and tiles
 * by city. Built once per turn phase instead of re-scanning per city pair.
 */
final class TileIndex
{
    /** @var array<string, int> */
    private array $ownerAt = [];

    /** @var array<int, array<int, Tile>> */
    private array $byCity = [];

    /** @param Collection<int, Tile> $tiles */
    public function __construct(Collection $tiles)
    {
        foreach ($tiles as $t) {
            if ($t->empire_id) {
                $this->ownerAt[$t->x.','.$t->y] = $t->empire_id;
            }
            if ($t->city_id) {
                $this->byCity[$t->city_id][] = $t;
            }
        }
    }

    public function ownerAt(int $x, int $y): ?int
    {
        return $this->ownerAt[$x.','.$y] ?? null;
    }

    /** @return array<int, Tile> */
    public function cityTiles(int $cityId): array
    {
        return $this->byCity[$cityId] ?? [];
    }

    /** Whether any tile of $empireId is adjacent (8-way) to the city's land. */
    public function touches(int $empireId, int $cityId): bool
    {
        foreach ($this->cityTiles($cityId) as $t) {
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    if ($this->ownerAt($t->x + $dx, $t->y + $dy) === $empireId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
