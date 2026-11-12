<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Support\Collection;

/**
 * Renders the game map as inline SVG: terrain with hand-drawn style
 * detail, coastlines, crisp territory borders along the outer edge of
 * each empire, cities scaled by size, resources, and the selection ring.
 * Every tile is an <a data-tile> so the page script can hook it.
 */
final class MapSvg
{
    public const TILE = 44;

    private const PALETTE = [
        'ocean' => '#123f73',
        'coast' => '#1c5a99',
        'grassland' => '#5f9a33',
        'plains' => '#b9a04a',
        'forest' => '#2f6b2c',
        'hills' => '#8f7e5f',
        'desert' => '#dcc57e',
        'mountain' => '#6e6862',
    ];

    /** @var array<int, array<int, Tile>> */
    private array $grid = [];

    /** @var array<int, true>|null tile ids the viewer can see; null = all */
    private ?array $visible = null;

    /** @var array<int, int> empire id => garrison per city */
    private array $garrisons = [];

    /**
     * @param  Collection<int, Empire>  $empiresById
     * @param  Collection<int, City>  $citiesByTile
     * @param  array<int, true>  $worked
     */
    public function render(Game $game, Collection $tiles, Collection $empiresById, Collection $citiesByTile, array $worked, ?int $selectedId, string $tileUrlBase, array $foundable = [], ?int $bestSiteId = null, ?array $visible = null, array $raids = [], ?int $goldenEmpireId = null): string
    {
        $this->visible = $visible;
        $this->garrisons = [];
        foreach ($empiresById as $e) {
            $this->garrisons[$e->id] = intdiv($e->army, max(1, $e->cities->count()));
        }
        $s = self::TILE;
        $w = $game->width * $s;
        $h = $game->height * $s;
        foreach ($tiles as $t) {
            $this->grid[$t->y][$t->x] = $t;
        }

        $out = [];
        $out[] = '<svg id="map-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" class="block h-full w-full select-none" preserveAspectRatio="xMidYMid meet" role="img" aria-label="World map">';
        $out[] = $this->defs();
        $out[] = '<pattern id="speckle" width="23" height="19" patternUnits="userSpaceOnUse"><circle cx="3" cy="4" r="0.9" fill="#000" opacity="0.35"/><circle cx="14" cy="9" r="0.7" fill="#fff" opacity="0.3"/><circle cx="8" cy="15" r="1" fill="#000" opacity="0.25"/><circle cx="19" cy="2" r="0.6" fill="#fff" opacity="0.25"/><circle cx="20" cy="15" r="0.8" fill="#000" opacity="0.3"/></pattern>';
        $out[] = '<marker id="raid-head" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto"><path d="M0 0 L10 5 L0 10 z" fill="#ef4444"/></marker>';
        $out[] = '<pattern id="fogp" width="10" height="10" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><rect width="10" height="10" fill="#0b0f15"/><rect width="10" height="3" fill="#121922"/></pattern>';

        // Layer 1: terrain (unexplored tiles are fog)
        $out[] = '<g id="terrain">';
        foreach ($tiles as $t) {
            $out[] = $this->fogged($t) ? sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="url(#fogp)"/>', $t->x * $s, $t->y * $s, $s, $s) : $this->terrainTile($t, $s);
        }
        $out[] = '</g>';

        // Layer 1a: texture, then shorelines (sand on land, foam in the water)
        $out[] = sprintf('<rect width="%d" height="%d" fill="url(#speckle)" opacity="0.5" style="pointer-events:none"/>', $w, $h);
        $sand = [];
        $foam = [];
        foreach ($tiles as $t) {
            if (! $t->isLand() || $this->fogged($t)) {
                continue;
            }
            $x = $t->x * $s;
            $y = $t->y * $s;
            foreach ([[0, -1, "M$x $y h$s", 'M'.$x.' '.($y - 4)." h$s"], [0, 1, 'M'.$x.' '.($y + $s)." h$s", 'M'.$x.' '.($y + $s + 4)." h$s"],
                [-1, 0, "M$x $y v$s", 'M'.($x - 4)." $y v$s"], [1, 0, 'M'.($x + $s)." $y v$s", 'M'.($x + $s + 4)." $y v$s"]] as [$dx, $dy, $edge, $off]) {
                $n = $this->grid[$t->y + $dy][$t->x + $dx] ?? null;
                if ($n && ! $n->isLand()) {
                    $sand[] = $edge;
                    $foam[] = $off;
                }
            }
        }
        if ($sand) {
            $out[] = '<g id="shores" fill="none" style="pointer-events:none" stroke-linecap="round">';
            $out[] = '<path d="'.implode(' ', $foam).'" stroke="#dbeafe" stroke-width="1.5" opacity="0.35" stroke-dasharray="5 4" class="foam"/>';
            $out[] = '<path d="'.implode(' ', $sand).'" stroke="#e9d8a6" stroke-width="3.5" opacity="0.85"/>';
            $out[] = '</g>';
        }

        // Layer 1b: rivers, joining adjacent river tiles centre to centre
        $out[] = '<g id="rivers" fill="none" stroke-linecap="round" stroke-linejoin="round">';
        $segments = [];
        foreach ($tiles as $t) {
            if (! $t->river || $this->fogged($t)) {
                continue;
            }
            $cx = $t->x * $s + $s / 2;
            $cy = $t->y * $s + $s / 2;
            $joined = false;
            foreach ([[1, 0], [0, 1], [-1, 0], [0, -1]] as [$dx, $dy]) {
                $n = $this->grid[$t->y + $dy][$t->x + $dx] ?? null;
                if (! $n) {
                    continue;
                }
                if ($n->river && ($dx > 0 || $dy > 0)) {
                    $segments[] = sprintf('M%s %s L%s %s', $cx, $cy, $n->x * $s + $s / 2, $n->y * $s + $s / 2);
                    $joined = true;
                } elseif (! $n->isLand()) {
                    // river mouth: run to the edge facing the sea
                    $segments[] = sprintf('M%s %s L%s %s', $cx, $cy, $cx + $dx * $s / 2, $cy + $dy * $s / 2);
                    $joined = true;
                }
            }
            if (! $joined) {
                $segments[] = sprintf('M%s %s l0.1 0', $cx, $cy);
            }
        }
        if ($segments) {
            $d = implode(' ', $segments);
            $out[] = '<path d="'.$d.'" stroke="#0b2a52" stroke-width="7" opacity="0.9"/>';
            $out[] = '<path d="'.$d.'" stroke="#3b82f6" stroke-width="4"/>';
            $out[] = '<path d="'.$d.'" stroke="#bfdbfe" stroke-width="1" opacity="0.6" class="river-shimmer" stroke-dasharray="6 10"/>';
        }
        $out[] = '</g>';

        // Layer 2: territory tint
        $out[] = '<g id="territory" opacity="0.22">';
        foreach ($tiles as $t) {
            if ($t->empire_id && ! $this->fogged($t) && ($e = $empiresById->get($t->empire_id))) {
                $out[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>', $t->x * $s, $t->y * $s, $s, $s, e($e->color));
            }
        }
        $out[] = '</g>';
        // A golden age bathes the empire's land in warm light.
        if ($goldenEmpireId) {
            $out[] = '<g id="golden" class="golden-glow" style="pointer-events:none">';
            foreach ($tiles as $t) {
                if ($t->empire_id === $goldenEmpireId && ! $this->fogged($t)) {
                    $out[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="#fbbf24"/>', $t->x * $s, $t->y * $s, $s, $s);
                }
            }
            $out[] = '</g>';
        }

        // Layer 3: borders on edges between different owners
        $out[] = '<g id="borders" stroke-width="3" stroke-linecap="square" fill="none">';
        foreach ($tiles as $t) {
            if (! $t->empire_id || $this->fogged($t)) {
                continue;
            }
            $color = e($empiresById->get($t->empire_id)?->color ?? '#fff');
            $x = $t->x * $s;
            $y = $t->y * $s;
            $edges = [];
            if ($this->ownerAt($t->x, $t->y - 1) !== $t->empire_id) {
                $edges[] = "M$x $y h$s";
            }
            if ($this->ownerAt($t->x, $t->y + 1) !== $t->empire_id) {
                $edges[] = 'M'.$x.' '.($y + $s)." h$s";
            }
            if ($this->ownerAt($t->x - 1, $t->y) !== $t->empire_id) {
                $edges[] = "M$x $y v$s";
            }
            if ($this->ownerAt($t->x + 1, $t->y) !== $t->empire_id) {
                $edges[] = 'M'.($x + $s)." $y v$s";
            }
            if ($edges) {
                $out[] = '<path d="'.implode(' ', $edges).'" stroke="'.$color.'"/>';
            }
        }
        $out[] = '</g>';

        // Layer 3b: roads from each city to its nearest sister city
        $roads = [];
        $centres = [];
        foreach ($citiesByTile as $tileId => $c) {
            $ct = $tiles->firstWhere('id', $tileId);
            if ($ct && ! $this->fogged($ct)) {
                $centres[] = [$c, $ct];
            }
        }
        foreach ($centres as [$c, $ct]) {
            $best = null;
            foreach ($centres as [$o, $ot]) {
                if ($o->id === $c->id || $o->empire_id !== $c->empire_id) {
                    continue;
                }
                $d = $ct->distanceTo($ot);
                if ($d <= 7 && ($best === null || $d < $best[0])) {
                    $best = [$d, $o, $ot];
                }
            }
            if ($best) {
                $key = min($c->id, $best[1]->id).'-'.max($c->id, $best[1]->id);
                $x1 = $ct->x * $s + $s / 2;
                $y1 = $ct->y * $s + $s / 2;
                $x2 = $best[2]->x * $s + $s / 2;
                $y2 = $best[2]->y * $s + $s / 2;
                // A gentle bend so roads do not look ruled.
                $mx = ($x1 + $x2) / 2 + ($y2 - $y1) * 0.12;
                $my = ($y1 + $y2) / 2 - ($x2 - $x1) * 0.12;
                $roads[$key] = sprintf('M%s %s Q%s %s %s %s', $x1, $y1, round($mx, 1), round($my, 1), $x2, $y2);
            }
        }
        // Trade routes: gold dotted arcs between partner cities
        $routes = [];
        $byCityId = [];
        foreach ($centres as [$c, $ct]) {
            $byCityId[$c->id] = $ct;
        }
        foreach ($centres as [$c, $ct]) {
            if ($c->trade_to && isset($byCityId[$c->trade_to])) {
                $o = $byCityId[$c->trade_to];
                $x1 = $ct->x * $s + $s / 2;
                $y1 = $ct->y * $s + $s / 2;
                $x2 = $o->x * $s + $s / 2;
                $y2 = $o->y * $s + $s / 2;
                $mx = ($x1 + $x2) / 2 - ($y2 - $y1) * 0.25;
                $my = ($y1 + $y2) / 2 + ($x2 - $x1) * 0.25;
                $routes[] = sprintf('M%s %s Q%s %s %s %s', $x1, $y1, round($mx, 1), round($my, 1), $x2, $y2);
            }
        }
        if ($roads) {
            $d = implode(' ', $roads);
            $out[] = '<g id="roads" fill="none" stroke-linecap="round" style="pointer-events:none">';
            $out[] = '<path d="'.$d.'" stroke="#3f2a14" stroke-width="5" opacity="0.55"/>';
            $out[] = '<path d="'.$d.'" stroke="#c8a165" stroke-width="2.2" stroke-dasharray="6 4" opacity="0.9"/>';
            $out[] = '</g>';
        }
        if ($routes) {
            $out[] = '<g id="trade-routes" fill="none" stroke-linecap="round" style="pointer-events:none">';
            $out[] = '<path d="'.implode(' ', $routes).'" stroke="#fbbf24" stroke-width="2.5" stroke-dasharray="0.1 7" opacity="0.95" class="trade-route"/>';
            $out[] = '</g>';
        }

        // Layer 4: worked markers, resources, cities
        $out[] = '<g id="features">';
        foreach ($tiles as $t) {
            if ($this->fogged($t)) {
                continue;
            }
            $cx = $t->x * $s + $s / 2;
            $cy = $t->y * $s + $s / 2;
            $city = $citiesByTile->get($t->id);
            if (isset($worked[$t->id]) && ! $city) {
                $out[] = sprintf('<circle cx="%d" cy="%d" r="2.5" fill="#fff" opacity="0.85"/>', $t->x * $s + $s - 7, $t->y * $s + $s - 7);
            }
            if ($t->camp) {
                $out[] = sprintf('<g style="pointer-events:none"><circle cx="%s" cy="%s" r="11" fill="#1c1917" stroke="#ef4444" stroke-width="2"/><text x="%s" y="%s" font-size="14" text-anchor="middle" dominant-baseline="central">☠</text></g>', $cx, $cy, $cx, $cy + 0.5);

                continue;
            }
            if ($t->feature && isset(Rules::NATURAL_WONDERS[$t->feature])) {
                $nw = Rules::NATURAL_WONDERS[$t->feature];
                $out[] = sprintf('<g style="pointer-events:none"><circle cx="%s" cy="%s" r="15" fill="%s" fill-opacity="0.25" stroke="%s" stroke-width="1.5" class="natural-wonder"/><text x="%s" y="%s" font-size="17" text-anchor="middle" dominant-baseline="central">%s</text></g>', $cx, $cy, e($nw['color']), e($nw['color']), $cx, $cy + 0.5, $nw['glyph']);
            } elseif ($t->resource && ! $city) {
                $out[] = sprintf('<use href="#res-%s" x="%d" y="%d" style="pointer-events:none"/>', $t->resource, $t->x * $s, $t->y * $s);
            }
            if ($city) {
                $e = $empiresById->get($city->empire_id);
                $out[] = $this->city($city, $e, $cx, $cy, $s);
            }
        }
        $out[] = '</g>';

        // Layer 4a: the edge of the known world, shaded so the fog reads as distance
        if ($this->visible !== null) {
            $out[] = '<g id="fog-edge" style="pointer-events:none">';
            foreach ($tiles as $t) {
                if ($this->fogged($t)) {
                    continue;
                }
                $near = 0;
                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [-1, -1], [1, -1], [-1, 1]] as [$dx, $dy]) {
                    $n = $this->grid[$t->y + $dy][$t->x + $dx] ?? null;
                    if ($n && $this->fogged($n)) {
                        $near++;
                    }
                }
                if ($near) {
                    $out[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="#0b0f15" opacity="%s"/>', $t->x * $s, $t->y * $s, $s, $s, min(0.55, 0.12 + 0.07 * $near));
                }
            }
            $out[] = '</g>';
        }

        // Layer 4a2: barbarian war bands on the march, camp to target city
        if ($raids) {
            $byId = $tiles->keyBy('id');
            $out[] = '<g id="raids" style="pointer-events:none" fill="none">';
            foreach ($raids as $r) {
                $from = $byId->get($r['camp']);
                $city = $citiesByTile->first(fn ($c) => $c->id === $r['city']);
                $to = $city ? $byId->get($city->tile_id) : null;
                if (! $from || ! $to || ($this->fogged($from) && $this->fogged($to))) {
                    continue;
                }
                $x1 = $from->x * $s + $s / 2;
                $y1 = $from->y * $s + $s / 2;
                $x2 = $to->x * $s + $s / 2;
                $y2 = $to->y * $s + $s / 2;
                $len = max(1, hypot($x2 - $x1, $y2 - $y1));
                $ex = $x2 - ($x2 - $x1) / $len * 18;
                $ey = $y2 - ($y2 - $y1) / $len * 18;
                $out[] = sprintf('<path d="M%s %s L%s %s" stroke="#000" stroke-width="6" opacity="0.5"/><path d="M%s %s L%s %s" stroke="#ef4444" stroke-width="3" stroke-dasharray="8 5" class="raid-path" marker-end="url(#raid-head)"/>',
                    $x1, $y1, round($ex, 1), round($ey, 1), $x1, $y1, round($ex, 1), round($ey, 1));
                $out[] = sprintf('<text x="%s" y="%s" font-size="12" font-weight="700" text-anchor="middle" fill="#fecaca" style="paint-order:stroke;stroke:#000;stroke-width:3px">☠ %d</text>', round(($x1 + $ex) / 2, 1), round(($y1 + $ey) / 2 - 6, 1), $r['strength']);
            }
            $out[] = '</g>';
        }

        // Layer 4b: where settlers may found a city, and the best spot
        if ($foundable) {
            $out[] = '<g id="founding" style="pointer-events:none">';
            foreach ($tiles as $t) {
                if (! isset($foundable[$t->id])) {
                    continue;
                }
                $out[] = sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="4" fill="#22c55e" fill-opacity="0.28" stroke="#4ade80" stroke-width="1.5" class="foundable"/>', $t->x * $s + 3, $t->y * $s + 3, $s - 6, $s - 6);
                if ($t->id === $bestSiteId) {
                    $out[] = sprintf('<text x="%s" y="%s" font-size="22" text-anchor="middle" dominant-baseline="central" fill="#fde68a" style="paint-order:stroke;stroke:#000;stroke-width:2px" class="best-site">★</text>', $t->x * $s + $s / 2, $t->y * $s + $s / 2);
                }
            }
            $out[] = '</g>';
        }

        // Layer 5: click targets + tooltips + selection
        $out[] = '<g id="targets">';
        foreach ($tiles as $t) {
            $city = $citiesByTile->get($t->id);
            $e = $t->empire_id ? $empiresById->get($t->empire_id) : null;
            $title = $this->fogged($t) ? 'Unexplored' : Rules::TERRAIN[$t->terrain]['name']
                .($t->resource ? ', '.Rules::RESOURCES[$t->resource]['name'] : '')
                .($t->camp ? ' · Barbarian camp' : '')
                .($t->river && $t->isLand() ? ' · River' : '')
                .($t->feature ? ' · '.Rules::NATURAL_WONDERS[$t->feature]['name'] : '')
                .($e ? ' · '.$e->name : '')
                .($city ? ' · '.$city->name.' ('.$city->population.')' : '');
            $sel = $selectedId === $t->id ? ' is-selected' : '';
            $out[] = sprintf(
                '<a href="%s?tile=%d" data-tile="%d" data-x="%d" data-y="%d" class="map-tile%s"><rect x="%d" y="%d" width="%d" height="%d" fill="transparent"/><title>%s</title></a>',
                e($tileUrlBase), $t->id, $t->id, $t->x, $t->y, $sel, $t->x * $s, $t->y * $s, $s, $s, e($title)
            );
        }
        $out[] = '</g>';

        if ($selectedId && ($sel = $tiles->firstWhere('id', $selectedId))) {
            $out[] = sprintf('<rect id="selection" x="%s" y="%s" width="%s" height="%s" fill="none" stroke="#fff" stroke-width="3" rx="3" style="pointer-events:none"/>',
                $sel->x * $s + 1.5, $sel->y * $s + 1.5, $s - 3, $s - 3);
        }

        $out[] = '</svg>';

        return implode("\n", $out);
    }

    private function adjust(string $hex, int $delta): string
    {
        $r = max(0, min(255, hexdec(substr($hex, 1, 2)) + $delta));
        $g = max(0, min(255, hexdec(substr($hex, 3, 2)) + $delta));
        $b = max(0, min(255, hexdec(substr($hex, 5, 2)) + $delta));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** A tiny overview: one rect per tile with owner or terrain colour. */
    public function minimap(Game $game, Collection $tiles, Collection $empiresById, Collection $citiesByTile, ?array $visible = null): string
    {
        $this->visible = $visible;
        $out = ['<svg id="minimap-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$game->width.' '.$game->height.'" shape-rendering="crispEdges" class="block h-auto w-full rounded border border-stone-700 bg-stone-950">'];
        foreach ($tiles as $t) {
            $fill = $t->isLand() ? ($t->terrain === 'mountain' ? '#57534e' : '#6b8f3a') : '#1e3a8a';
            if ($this->fogged($t)) {
                $fill = '#0b0f15';
            } elseif ($t->empire_id && ($e = $empiresById->get($t->empire_id))) {
                $fill = $e->color;
            }
            $out[] = sprintf('<rect x="%d" y="%d" width="1" height="1" fill="%s"/>', $t->x, $t->y, e($fill));
        }
        foreach ($citiesByTile as $city) {
            $t = $tiles->firstWhere('id', $city->tile_id);
            if ($t && ! $this->fogged($t)) {
                $out[] = sprintf('<rect x="%s" y="%s" width="0.6" height="0.6" fill="#fff"/>', $t->x + 0.2, $t->y + 0.2);
            }
        }
        $out[] = '<rect id="minimap-view" x="0" y="0" width="'.$game->width.'" height="'.$game->height.'" fill="none" stroke="#fff" stroke-width="0.35" style="pointer-events:none"/>';
        $out[] = '</svg>';

        return implode('', $out);
    }

    private function fogged(Tile $t): bool
    {
        return $this->visible !== null && ! isset($this->visible[$t->id]);
    }

    private function ownerAt(int $x, int $y): ?int
    {
        return isset($this->grid[$y][$x]) ? $this->grid[$y][$x]->empire_id : null;
    }

    private function landWithin(int $x, int $y, int $r): bool
    {
        for ($dy = -$r; $dy <= $r; $dy++) {
            for ($dx = -$r; $dx <= $r; $dx++) {
                if (($dx || $dy) && $this->isLandAt($x + $dx, $y + $dy)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isLandAt(int $x, int $y): bool
    {
        return isset($this->grid[$y][$x]) && $this->grid[$y][$x]->isLand();
    }

    private function terrainTile(Tile $t, int $s): string
    {
        $x = $t->x * $s;
        $y = $t->y * $s;
        $terrain = $t->terrain;
        $fill = self::PALETTE[$terrain];
        $detail = '';
        // Subtle per-tile shade so large plains do not read as flat colour.
        $shade = (($t->x * 37 + $t->y * 61) % 7) - 3; // -3..3
        $fill = $this->adjust($fill, $shade * 3);

        if ($terrain === 'ocean') {
            $coast = $this->isLandAt($t->x - 1, $t->y) || $this->isLandAt($t->x + 1, $t->y) || $this->isLandAt($t->x, $t->y - 1) || $this->isLandAt($t->x, $t->y + 1);
            if ($coast) {
                $fill = self::PALETTE['coast'];
            } elseif (! $this->landWithin($t->x, $t->y, 2)) {
                // Open sea darkens with distance from land.
                $fill = $this->adjust($this->landWithin($t->x, $t->y, 4) ? '#0f3666' : '#0a2a55', $shade * 2);
            }
            // deterministic wave placement
            $k = ($t->x * 7 + $t->y * 13) % 3;
            $detail = sprintf('<use href="#wave" x="%d" y="%d" opacity="0.5" class="wave w%d"/>', $x + 6 + $k * 4, $y + 12 + $k * 9, $k);
            if (! $coast && (($t->x * 3 + $t->y * 5) % 4) === 0) {
                $detail .= sprintf('<use href="#wave" x="%d" y="%d" opacity="0.3" class="wave w%d"/>', $x + 18, $y + 30, ($k + 1) % 3);
            }
        } else {
            $detail = match ($terrain) {
                'forest' => sprintf('<use href="#trees" x="%d" y="%d"/>', $x, $y),
                'hills' => sprintf('<use href="#hills" x="%d" y="%d"/>', $x, $y),
                'mountain' => sprintf('<use href="#peak" x="%d" y="%d"/>', $x, $y),
                'desert' => sprintf('<use href="#dunes" x="%d" y="%d"/>', $x, $y),
                'grassland' => sprintf('<use href="#grass" x="%d" y="%d"/>', $x, $y),
                'plains' => sprintf('<use href="#plain" x="%d" y="%d"/>', $x, $y),
                default => '',
            };
        }

        return sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>%s', $x, $y, $s, $s, $fill, $detail);
    }

    private function city(City $city, ?Empire $e, float $cx, float $cy, int $s): string
    {
        $color = e($e?->color ?? '#fff');
        $r = min(16, 9 + intdiv($city->population, 1));
        $name = e($city->name);
        $star = $city->is_capital
            ? sprintf('<g style="pointer-events:none"><circle cx="%1$s" cy="%2$s" r="7" fill="#1c1917" stroke="#fde68a" stroke-width="1.2"/><text x="%1$s" y="%3$s" font-size="9" text-anchor="middle" dominant-baseline="central">%4$s</text></g>', $cx + $r - 1, $cy - $r + 1, $cy - $r + 1.5, Rules::crest($e?->name ?? ''))
            : '';
        $hasWonder = false;
        foreach ($city->buildings ?? [] as $b) {
            $hasWonder = $hasWonder || Rules::isWonder($b);
        }
        $walls = $city->has('walls')
            ? sprintf('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="#e7e5e4" stroke-width="2" stroke-dasharray="3 2"/>', $cx, $cy, $r + 3)
            : '';
        $glow = $hasWonder
            ? sprintf('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="#fbbf24" stroke-width="1.5" opacity="0.9"/>', $cx, $cy, $r + 6)
            : '';

        $garrison = $this->garrisons[$city->empire_id] ?? 0;
        $shield = sprintf('<g style="pointer-events:none"><path d="M%1$s %2$s l5 -2 l5 2 v5 q0 4 -5 6 q-5 -2 -5 -6 z" fill="#1c1917" stroke="#e7e5e4" stroke-width="1"/><text x="%3$s" y="%4$s" font-size="7.5" font-weight="700" text-anchor="middle" dominant-baseline="central" fill="#fff">%5$d</text></g>',
            $cx - $r - 4, $cy - $r - 2, $cx - $r + 1, $cy - $r + 4, $garrison);

        return $walls.$glow.sprintf(
            '<g class="city" style="pointer-events:none">'
            .'<circle cx="%1$s" cy="%2$s" r="%3$s" fill="%4$s" stroke="#111" stroke-width="2"/>'
            .'<circle cx="%1$s" cy="%2$s" r="%5$s" fill="none" stroke="#fff" stroke-opacity="0.6" stroke-width="1"/>'
            .'<text x="%1$s" y="%2$s" font-size="%6$s" font-weight="700" text-anchor="middle" dominant-baseline="central" fill="#fff" style="paint-order:stroke;stroke:#000;stroke-width:2px">%7$d</text>'
            .'%8$s'
            .'<text x="%1$s" y="%9$s" font-size="9.5" font-weight="600" text-anchor="middle" fill="#fff" style="paint-order:stroke;stroke:#000;stroke-width:2.5px;letter-spacing:.2px">%10$s</text>'
            .'</g>',
            $cx, $cy, $r, $color, $r - 3, $r >= 13 ? 12 : 11, $city->population, $star, $cy + $s / 2 - 3, $name
        ).$shield;
    }

    private function defs(): string
    {
        return <<<'SVG'
<defs>
  <symbol id="trees" viewBox="0 0 44 44" width="44" height="44">
    <g stroke="#0f2e12" stroke-width="0.6">
      <rect x="14" y="30" width="2" height="4" fill="#4a3220" stroke="none"/><path d="M9 31 l6 -12 l6 12 z" fill="#1f5a24"/><path d="M11 25 l4 -8 l4 8 z" fill="#2f7d33"/><path d="M12.5 20 l2.5 -5 l2.5 5 z" fill="#3f9a43"/>
      <rect x="28" y="33" width="2" height="4" fill="#4a3220" stroke="none"/><path d="M23 34 l6 -12 l6 12 z" fill="#1f5a24"/><path d="M25 28 l4 -8 l4 8 z" fill="#2f7d33"/>
      <rect x="29" y="18" width="1.6" height="3" fill="#4a3220" stroke="none"/><path d="M25 19 l5 -10 l5 10 z" fill="#1f5a24"/><path d="M26.5 14 l3.5 -7 l3.5 7 z" fill="#2f7d33"/>
      <path d="M6 36 l4 -7 l4 7 z" fill="#1f5a24"/>
    </g>
  </symbol>
  <symbol id="hills" viewBox="0 0 44 44" width="44" height="44">
    <path d="M4 32 q9 -14 18 0 z" fill="#a8956f" opacity="0.8"/><path d="M4 32 q9 -14 18 0" fill="none" stroke="#5c4f38" stroke-width="1.5"/>
    <path d="M18 36 q10 -15 20 0 z" fill="#9c8a66" opacity="0.8"/><path d="M18 36 q10 -15 20 0" fill="none" stroke="#5c4f38" stroke-width="1.5"/>
    <path d="M12 22 q7 -10 14 0 z" fill="#b3a07a" opacity="0.7"/><path d="M12 22 q7 -10 14 0" fill="none" stroke="#5c4f38" stroke-width="1.2"/>
  </symbol>
  <symbol id="peak" viewBox="0 0 44 44" width="44" height="44">
    <path d="M3 37 L17 11 L24 22 L30 13 L41 37 Z" fill="#6b645d" stroke="#2f2b28" stroke-width="1"/>
    <path d="M17 11 L24 22 L30 13 L41 37 L22 37 Z" fill="#4a443f" opacity="0.7"/>
    <path d="M17 11 L21 18 L24 22 L26 18 L30 13 L32 18 L28 18 L26 22 L22 21 L19 18 L14 17 Z" fill="#f4f1ea"/>
    <path d="M24 22 L26 18 L30 13 L32 18 L28 18 L26 22 Z" fill="#d6d3d1"/>
  </symbol>
  <symbol id="dunes" viewBox="0 0 44 44" width="44" height="44">
    <g fill="none" stroke="#b89e52" stroke-width="1.5" stroke-linecap="round" opacity="0.8">
      <path d="M6 18 q6 -4 12 0"/><path d="M22 30 q7 -4 14 0"/><path d="M8 34 q5 -3 10 0"/>
    </g>
  </symbol>
  <symbol id="grass" viewBox="0 0 44 44" width="44" height="44">
    <g stroke="#3f7a1f" stroke-width="1.3" stroke-linecap="round" opacity="0.7">
      <path d="M10 30 v-5"/><path d="M13 31 v-4"/><path d="M28 18 v-5"/><path d="M31 19 v-4"/><path d="M20 36 v-4"/>
    </g>
  </symbol>
  <symbol id="plain" viewBox="0 0 44 44" width="44" height="44">
    <g stroke="#8d7a33" stroke-width="1.2" stroke-linecap="round" opacity="0.6">
      <path d="M8 26 h8"/><path d="M26 16 h8"/><path d="M22 34 h8"/>
    </g>
  </symbol>
  <symbol id="res-wheat" viewBox="0 0 44 44" width="44" height="44">
    <g stroke="#facc15" stroke-width="1.6" stroke-linecap="round" fill="#fde047">
      <path d="M22 36 V16"/><path d="M16 36 V20"/><path d="M28 36 V20"/>
      <ellipse cx="22" cy="14" rx="2.5" ry="4"/><ellipse cx="16" cy="18" rx="2.2" ry="3.5"/><ellipse cx="28" cy="18" rx="2.2" ry="3.5"/>
      <ellipse cx="19" cy="20" rx="2" ry="3" transform="rotate(-25 19 20)"/><ellipse cx="25" cy="20" rx="2" ry="3" transform="rotate(25 25 20)"/>
    </g>
  </symbol>
  <symbol id="res-cattle" viewBox="0 0 44 44" width="44" height="44">
    <ellipse cx="22" cy="25" rx="10" ry="6.5" fill="#7c4a2a" stroke="#3b2314" stroke-width="1"/>
    <circle cx="31" cy="21" r="4.5" fill="#8b5a36" stroke="#3b2314" stroke-width="1"/>
    <path d="M28 17 q-1 -4 2 -5 M34 17 q1 -4 -2 -5" stroke="#f5f5f4" stroke-width="1.6" fill="none" stroke-linecap="round"/>
    <path d="M15 30 v5 M19 31 v5 M25 31 v5 M29 30 v5" stroke="#3b2314" stroke-width="1.8" stroke-linecap="round"/>
    <circle cx="19" cy="23" r="2" fill="#f5f5f4"/>
  </symbol>
  <symbol id="res-horses" viewBox="0 0 44 44" width="44" height="44">
    <path d="M13 34 v-9 q0 -7 7 -8 h6 l4 -6 l3 1 l-1 6 q3 2 2 7 v9" fill="#a16207" stroke="#3b2314" stroke-width="1" stroke-linejoin="round"/>
    <path d="M20 17 q4 -3 8 -1" stroke="#3b2314" stroke-width="2" fill="none" stroke-linecap="round"/>
    <path d="M15 34 v-6 M30 34 v-6" stroke="#3b2314" stroke-width="2"/>
    <circle cx="29" cy="15" r="1" fill="#1c1917"/>
  </symbol>
  <symbol id="res-iron" viewBox="0 0 44 44" width="44" height="44">
    <polygon points="12,32 16,22 24,24 26,32" fill="#6b7280" stroke="#1f2937" stroke-width="1"/>
    <polygon points="22,30 26,20 34,22 33,30" fill="#9ca3af" stroke="#1f2937" stroke-width="1"/>
    <polygon points="18,22 22,14 28,18 24,24" fill="#d1d5db" stroke="#1f2937" stroke-width="1"/>
  </symbol>
  <symbol id="res-gold" viewBox="0 0 44 44" width="44" height="44">
    <ellipse cx="18" cy="28" rx="7" ry="3" fill="#ca8a04" stroke="#713f12" stroke-width="1"/>
    <ellipse cx="18" cy="25" rx="7" ry="3" fill="#facc15" stroke="#713f12" stroke-width="1"/>
    <ellipse cx="27" cy="23" rx="7" ry="3" fill="#ca8a04" stroke="#713f12" stroke-width="1"/>
    <ellipse cx="27" cy="20" rx="7" ry="3" fill="#fde047" stroke="#713f12" stroke-width="1"/>
  </symbol>
  <symbol id="res-gems" viewBox="0 0 44 44" width="44" height="44">
    <polygon points="22,34 12,20 17,13 27,13 32,20" fill="#a78bfa" stroke="#4c1d95" stroke-width="1" stroke-linejoin="round"/>
    <path d="M12 20 h20 M17 13 l5 7 l5 -7 M22 20 v14" stroke="#ede9fe" stroke-width="1" opacity="0.8"/>
  </symbol>
  <symbol id="res-fish" viewBox="0 0 44 44" width="44" height="44">
    <path d="M12 22 q8 -9 18 0 q-8 9 -18 0 z" fill="#38bdf8" stroke="#0c4a6e" stroke-width="1"/>
    <polygon points="30,22 36,17 36,27" fill="#38bdf8" stroke="#0c4a6e" stroke-width="1"/>
    <circle cx="17" cy="21" r="1.2" fill="#0c4a6e"/>
  </symbol>
  <symbol id="wave" viewBox="0 0 20 8" width="20" height="8">
    <path d="M0 5 q3 -4 6 0 t6 0 t6 0" fill="none" stroke="#9cc9ff" stroke-width="1.2" stroke-linecap="round"/>
  </symbol>
</defs>
SVG;
    }
}
