<?php

namespace App\Game;

use Illuminate\Support\Facades\Cache;

/**
 * The front page's illustration: a real generated world (fixed seed) with
 * rivers and a few empires spreading across it. Rendered once and cached.
 */
final class HeroMap
{
    private const SEED = 20261113;

    private const W = 44;

    private const H = 22;

    private const S = 12;

    private const PALETTE = [
        'ocean' => '#123f73', 'coast' => '#1c5a99', 'grassland' => '#5f9a33', 'plains' => '#b9a04a',
        'forest' => '#2f6b2c', 'hills' => '#8f7e5f', 'desert' => '#dcc57e', 'mountain' => '#6e6862',
    ];

    private const EMPIRES = ['#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#22c55e'];

    public static function svg(): string
    {
        return Cache::rememberForever('hero-map-v2', fn () => self::render());
    }

    private static function render(): string
    {
        $tiles = (new MapGenerator(self::SEED, self::W, self::H))->generate();
        $grid = [];
        foreach ($tiles as $t) {
            $grid[$t['y']][$t['x']] = $t;
        }
        $land = fn ($x, $y) => isset($grid[$y][$x]) && $grid[$y][$x]['terrain'] !== 'ocean';
        $s = self::S;
        $out = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.(self::W * $s).' '.(self::H * $s).'" class="block h-auto w-full" role="img" aria-label="A generated world">'];
        // Terrain is softened into a painted look; rivers and empires stay crisp on top.
        $out[] = '<defs><filter id="hero-soft" x="-5%" y="-5%" width="110%" height="110%"><feGaussianBlur stdDeviation="2.2"/></filter>'
            .'<clipPath id="hero-clip"><rect width="'.(self::W * $s).'" height="'.(self::H * $s).'"/></clipPath></defs>';
        $out[] = '<rect width="100%" height="100%" fill="'.self::PALETTE['ocean'].'"/>';
        $out[] = '<g clip-path="url(#hero-clip)"><g filter="url(#hero-soft)" shape-rendering="crispEdges">';

        foreach ($tiles as $t) {
            $fill = self::PALETTE[$t['terrain']];
            if ($t['terrain'] === 'ocean' && ($land($t['x'] - 1, $t['y']) || $land($t['x'] + 1, $t['y']) || $land($t['x'], $t['y'] - 1) || $land($t['x'], $t['y'] + 1))) {
                $fill = self::PALETTE['coast'];
            }
            $out[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>', $t['x'] * $s, $t['y'] * $s, $s, $s, $fill);
        }
        $out[] = '</g></g>';

        // Rivers, joined centre to centre.
        $d = [];
        foreach ($tiles as $t) {
            if (! $t['river']) {
                continue;
            }
            foreach ([[1, 0], [0, 1]] as [$dx, $dy]) {
                if (! empty($grid[$t['y'] + $dy][$t['x'] + $dx]['river'])) {
                    $d[] = sprintf('M%s %s l%s %s', $t['x'] * $s + $s / 2, $t['y'] * $s + $s / 2, $dx * $s, $dy * $s);
                }
            }
        }
        if ($d) {
            $out[] = '<path d="'.implode(' ', $d).'" stroke="#60a5fa" stroke-width="2.5" stroke-linecap="round" fill="none"/>';
        }

        // A few empires: spread from well-separated land tiles.
        mt_srand(self::SEED);
        $seeds = [];
        $landTiles = array_values(array_filter($tiles, fn ($t) => ! in_array($t['terrain'], ['ocean', 'mountain'], true)));
        shuffle($landTiles);
        foreach ($landTiles as $t) {
            if (count($seeds) >= count(self::EMPIRES)) {
                break;
            }
            foreach ($seeds as $o) {
                if (abs($o['x'] - $t['x']) + abs($o['y'] - $t['y']) < 11) {
                    continue 2;
                }
            }
            $seeds[] = $t;
        }
        foreach ($seeds as $i => $c) {
            $color = self::EMPIRES[$i];
            $r = 2 + ($i % 2);
            $cells = [];
            for ($y = $c['y'] - $r; $y <= $c['y'] + $r; $y++) {
                for ($x = $c['x'] - $r; $x <= $c['x'] + $r; $x++) {
                    if ($land($x, $y) && ($x - $c['x']) ** 2 + ($y - $c['y']) ** 2 <= $r * $r + 1) {
                        $cells[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d"/>', $x * $s, $y * $s, $s, $s);
                    }
                }
            }
            $out[] = '<g fill="'.$color.'" opacity="0.32" shape-rendering="crispEdges">'.implode('', $cells).'</g>';
            $out[] = sprintf('<circle cx="%s" cy="%s" r="5" fill="%s" stroke="#111" stroke-width="1.5"/>', $c['x'] * $s + $s / 2, $c['y'] * $s + $s / 2, $color);
        }

        $out[] = '</svg>';

        return implode('', $out);
    }
}
