<?php

namespace App\Game;

/**
 * Seeded map generation. Returns a flat list of tile rows ready for insert.
 * Land is grown from random seeds so continents clump; the outer ring is
 * always ocean so borders never touch the edge awkwardly.
 */
final class MapGenerator
{
    private int $w;

    private int $h;

    public function __construct(private int $seed, int $width = Rules::MAP_WIDTH, int $height = Rules::MAP_HEIGHT)
    {
        $this->w = $width;
        $this->h = $height;
    }

    /** @return array<int, array{x:int,y:int,terrain:string,resource:?string}> */
    public function generate(): array
    {
        mt_srand($this->seed);

        $land = array_fill(0, $this->w * $this->h, false);
        $target = (int) round($this->w * $this->h * 0.62);
        $count = 0;

        // Several seeds, random-walked outward until the land budget is spent.
        $walkers = [];
        for ($i = 0; $i < 5; $i++) {
            $walkers[] = [mt_rand(2, $this->w - 3), mt_rand(2, $this->h - 3)];
        }
        $guard = 0;
        while ($count < $target && $guard++ < 50000) {
            foreach ($walkers as &$wk) {
                [$x, $y] = $wk;
                if ($x >= 1 && $x <= $this->w - 2 && $y >= 1 && $y <= $this->h - 2) {
                    $i = $y * $this->w + $x;
                    if (! $land[$i]) {
                        $land[$i] = true;
                        $count++;
                    }
                }
                $wk = match (mt_rand(0, 3)) {
                    0 => [min($this->w - 2, $x + 1), $y],
                    1 => [max(1, $x - 1), $y],
                    2 => [$x, min($this->h - 2, $y + 1)],
                    default => [$x, max(1, $y - 1)],
                };
                // Occasionally teleport back toward the middle so walkers do not
                // all pile up in one corner.
                if (mt_rand(0, 60) === 0) {
                    $wk = [mt_rand(2, $this->w - 3), mt_rand(2, $this->h - 3)];
                }
            }
            unset($wk);
        }

        $tiles = [];
        for ($y = 0; $y < $this->h; $y++) {
            for ($x = 0; $x < $this->w; $x++) {
                $i = $y * $this->w + $x;
                if (! $land[$i]) {
                    $tiles[] = ['x' => $x, 'y' => $y, 'terrain' => 'ocean', 'resource' => mt_rand(1, 100) <= 10 ? 'fish' : null, 'river' => false];

                    continue;
                }
                $terrain = $this->pickTerrain($y);
                $tiles[] = ['x' => $x, 'y' => $y, 'terrain' => $terrain, 'resource' => $this->pickResource($terrain), 'river' => false];
            }
        }

        $this->carveRivers($tiles, $land);

        // Smooth lonely mountains next to the coast into hills so start sites
        // are not boxed in.
        foreach ($tiles as $k => $t) {
            if ($t['terrain'] === 'mountain') {
                $ocean = 0;
                foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
                    $nx = $t['x'] + $dx;
                    $ny = $t['y'] + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $this->w || $ny >= $this->h || ! $land[$ny * $this->w + $nx]) {
                        $ocean++;
                    }
                }
                if ($ocean >= 2) {
                    $tiles[$k]['terrain'] = 'hills';
                }
            }
        }

        return $tiles;
    }

    /**
     * Rivers start in hills or mountains and flow toward the nearest ocean,
     * following a breadth-first distance field so they never wander uphill
     * forever. Marked on tiles; the renderer joins adjacent river tiles.
     */
    private function carveRivers(array &$tiles, array $land): void
    {
        $w = $this->w;
        $h = $this->h;
        // Distance to ocean.
        $dist = array_fill(0, $w * $h, PHP_INT_MAX);
        $queue = [];
        foreach ($land as $i => $isLand) {
            if (! $isLand) {
                $dist[$i] = 0;
                $queue[] = $i;
            }
        }
        $head = 0;
        while ($head < count($queue)) {
            $i = $queue[$head++];
            $x = $i % $w;
            $y = intdiv($i, $w);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                    continue;
                }
                $j = $ny * $w + $nx;
                if ($dist[$j] > $dist[$i] + 1) {
                    $dist[$j] = $dist[$i] + 1;
                    $queue[] = $j;
                }
            }
        }

        $sources = [];
        foreach ($tiles as $k => $t) {
            if (in_array($t['terrain'], ['hills', 'mountain'], true) && $dist[$k] >= 3) {
                $sources[] = $k;
            }
        }
        shuffle($sources);
        $want = max(2, intdiv($w * $h, 90));
        $isRiver = [];
        foreach (array_slice($sources, 0, $want * 3) as $start) {
            if (count($isRiver) >= $want * 9) {
                break;
            }
            // Keep sources apart.
            $sx = $start % $w;
            $sy = intdiv($start, $w);
            $tooClose = false;
            foreach ($isRiver as $r => $_) {
                if (abs($r % $w - $sx) <= 2 && abs(intdiv($r, $w) - $sy) <= 2) {
                    $tooClose = true;
                    break;
                }
            }
            if ($tooClose) {
                continue;
            }
            $i = $start;
            for ($step = 0; $step < 14; $step++) {
                $isRiver[$i] = true;
                $x = $i % $w;
                $y = intdiv($i, $w);
                $best = null;
                $bestD = $dist[$i];
                $options = [[1, 0], [-1, 0], [0, 1], [0, -1]];
                shuffle($options);
                foreach ($options as [$dx, $dy]) {
                    $nx = $x + $dx;
                    $ny = $y + $dy;
                    if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) {
                        continue;
                    }
                    $j = $ny * $w + $nx;
                    if (! $land[$j]) {
                        $best = null; // reached the sea
                        $bestD = -1;
                        break;
                    }
                    if ($dist[$j] <= $bestD && ! isset($isRiver[$j])) {
                        // Allow sideways meanders (equal distance) sometimes.
                        if ($dist[$j] < $bestD || mt_rand(0, 2) === 0) {
                            $best = $j;
                            $bestD = $dist[$j];
                        }
                    }
                }
                if ($best === null) {
                    break;
                }
                $i = $best;
            }
        }
        foreach ($isRiver as $k => $_) {
            $tiles[$k]['river'] = true;
            if ($tiles[$k]['terrain'] === 'mountain') {
                $tiles[$k]['terrain'] = 'hills'; // a river valley
            }
        }
    }

    private function pickTerrain(int $y): string
    {
        // Mild latitude: deserts favour the middle band, forests the edges.
        $mid = abs($y - $this->h / 2) / ($this->h / 2); // 0 centre … 1 edge
        $r = mt_rand(1, 100);
        $desert = (int) round(10 * (1 - $mid));
        $forest = (int) round(14 + 10 * $mid);

        if ($r <= 6) {
            return 'mountain';
        }
        if ($r <= 6 + $desert) {
            return 'desert';
        }
        if ($r <= 6 + $desert + $forest) {
            return 'forest';
        }
        if ($r <= 6 + $desert + $forest + 16) {
            return 'hills';
        }
        if ($r <= 6 + $desert + $forest + 16 + 26) {
            return 'plains';
        }

        return 'grassland';
    }

    private function pickResource(string $terrain): ?string
    {
        if (mt_rand(1, 100) > 14) {
            return null;
        }

        $pool = match ($terrain) {
            'grassland' => ['wheat', 'wheat', 'cattle', 'horses'],
            'plains' => ['wheat', 'cattle', 'horses', 'horses'],
            'forest' => ['cattle', 'iron'],
            'hills' => ['iron', 'iron', 'gold', 'gems'],
            'desert' => ['gold', 'gems', 'iron'],
            default => [],
        };

        return $pool ? $pool[array_rand($pool)] : null;
    }
}
