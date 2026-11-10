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

    /**
     * @param  Collection<int, Empire>  $empiresById
     * @param  Collection<int, City>  $citiesByTile
     * @param  array<int, true>  $worked
     */
    public function render(Game $game, Collection $tiles, Collection $empiresById, Collection $citiesByTile, array $worked, ?int $selectedId, string $tileUrlBase): string
    {
        $s = self::TILE;
        $w = $game->width * $s;
        $h = $game->height * $s;
        foreach ($tiles as $t) {
            $this->grid[$t->y][$t->x] = $t;
        }

        $out = [];
        $out[] = '<svg id="map-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$h.'" class="block h-auto w-full select-none" style="min-width:'.($game->width * 30).'px" role="img" aria-label="World map">';
        $out[] = $this->defs();

        // Layer 1: terrain
        $out[] = '<g id="terrain">';
        foreach ($tiles as $t) {
            $out[] = $this->terrainTile($t, $s);
        }
        $out[] = '</g>';

        // Layer 2: territory tint
        $out[] = '<g id="territory" opacity="0.22">';
        foreach ($tiles as $t) {
            if ($t->empire_id && ($e = $empiresById->get($t->empire_id))) {
                $out[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>', $t->x * $s, $t->y * $s, $s, $s, e($e->color));
            }
        }
        $out[] = '</g>';

        // Layer 3: borders on edges between different owners
        $out[] = '<g id="borders" stroke-width="3" stroke-linecap="square" fill="none">';
        foreach ($tiles as $t) {
            if (! $t->empire_id) {
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

        // Layer 4: worked markers, resources, cities
        $out[] = '<g id="features">';
        foreach ($tiles as $t) {
            $cx = $t->x * $s + $s / 2;
            $cy = $t->y * $s + $s / 2;
            $city = $citiesByTile->get($t->id);
            if (isset($worked[$t->id]) && ! $city) {
                $out[] = sprintf('<circle cx="%d" cy="%d" r="2.5" fill="#fff" opacity="0.85"/>', $t->x * $s + $s - 7, $t->y * $s + $s - 7);
            }
            if ($t->resource && ! $city) {
                $out[] = sprintf('<text x="%s" y="%s" font-size="15" text-anchor="middle" dominant-baseline="central" style="pointer-events:none">%s</text>', $cx, $cy, Rules::RESOURCES[$t->resource]['icon']);
            }
            if ($city) {
                $e = $empiresById->get($city->empire_id);
                $out[] = $this->city($city, $e, $cx, $cy, $s);
            }
        }
        $out[] = '</g>';

        // Layer 5: click targets + tooltips + selection
        $out[] = '<g id="targets">';
        foreach ($tiles as $t) {
            $city = $citiesByTile->get($t->id);
            $e = $t->empire_id ? $empiresById->get($t->empire_id) : null;
            $title = Rules::TERRAIN[$t->terrain]['name']
                .($t->resource ? ', '.Rules::RESOURCES[$t->resource]['name'] : '')
                .($e ? ' · '.$e->name : '')
                .($city ? ' · '.$city->name.' ('.$city->population.')' : '');
            $sel = $selectedId === $t->id ? ' is-selected' : '';
            $out[] = sprintf(
                '<a href="%s?tile=%d" data-tile="%d" class="map-tile%s"><rect x="%d" y="%d" width="%d" height="%d" fill="transparent"/><title>%s</title></a>',
                e($tileUrlBase), $t->id, $t->id, $sel, $t->x * $s, $t->y * $s, $s, $s, e($title)
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

    private function ownerAt(int $x, int $y): ?int
    {
        return isset($this->grid[$y][$x]) ? $this->grid[$y][$x]->empire_id : null;
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

        if ($terrain === 'ocean') {
            $coast = $this->isLandAt($t->x - 1, $t->y) || $this->isLandAt($t->x + 1, $t->y) || $this->isLandAt($t->x, $t->y - 1) || $this->isLandAt($t->x, $t->y + 1);
            if ($coast) {
                $fill = self::PALETTE['coast'];
            }
            // deterministic wave placement
            $k = ($t->x * 7 + $t->y * 13) % 3;
            $detail = sprintf('<use href="#wave" x="%d" y="%d" opacity="0.5"/>', $x + 6 + $k * 4, $y + 12 + $k * 9);
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
        $r = min(16, 9 + $city->population);
        $name = e($city->name);
        $star = $city->is_capital ? sprintf('<text x="%s" y="%s" font-size="10" text-anchor="middle" fill="#fde68a" style="pointer-events:none">★</text>', $cx + $r - 2, $cy - $r + 4) : '';

        return sprintf(
            '<g class="city" style="pointer-events:none">'
            .'<circle cx="%1$s" cy="%2$s" r="%3$s" fill="%4$s" stroke="#111" stroke-width="2"/>'
            .'<circle cx="%1$s" cy="%2$s" r="%5$s" fill="none" stroke="#fff" stroke-opacity="0.6" stroke-width="1"/>'
            .'<text x="%1$s" y="%2$s" font-size="%6$s" font-weight="700" text-anchor="middle" dominant-baseline="central" fill="#fff" style="paint-order:stroke;stroke:#000;stroke-width:2px">%7$d</text>'
            .'%8$s'
            .'<text x="%1$s" y="%9$s" font-size="9.5" font-weight="600" text-anchor="middle" fill="#fff" style="paint-order:stroke;stroke:#000;stroke-width:2.5px;letter-spacing:.2px">%10$s</text>'
            .'</g>',
            $cx, $cy, $r, $color, $r - 3, $r >= 13 ? 12 : 11, $city->population, $star, $cy + $s / 2 - 3, $name
        );
    }

    private function defs(): string
    {
        return <<<'SVG'
<defs>
  <symbol id="trees" viewBox="0 0 44 44" width="44" height="44">
    <g fill="#1f4d1f" stroke="#163816" stroke-width="0.6">
      <path d="M10 30 l5 -10 l5 10 z"/><path d="M12 24 l3 -7 l3 7 z" fill="#2a6a2a"/>
      <path d="M24 34 l5 -11 l5 11 z"/><path d="M26 27 l3 -7 l3 7 z" fill="#2a6a2a"/>
      <path d="M26 18 l4 -8 l4 8 z"/><path d="M27.5 13 l2.5 -5 l2.5 5 z" fill="#2a6a2a"/>
    </g>
  </symbol>
  <symbol id="hills" viewBox="0 0 44 44" width="44" height="44">
    <g fill="none" stroke="#5c4f38" stroke-width="2" stroke-linecap="round" opacity="0.8">
      <path d="M6 30 q8 -12 16 0"/><path d="M20 34 q8 -12 16 0"/><path d="M14 20 q6 -9 12 0"/>
    </g>
  </symbol>
  <symbol id="peak" viewBox="0 0 44 44" width="44" height="44">
    <path d="M4 36 L17 12 L24 22 L30 14 L41 36 Z" fill="#5b554f" stroke="#2f2b28" stroke-width="1"/>
    <path d="M17 12 L21 19 L24 22 L26 18 L30 14 L32 18 L28 18 L26 22 L22 21 L19 18 L15 17 Z" fill="#f4f1ea"/>
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
  <symbol id="wave" viewBox="0 0 20 8" width="20" height="8">
    <path d="M0 5 q3 -4 6 0 t6 0 t6 0" fill="none" stroke="#9cc9ff" stroke-width="1.2" stroke-linecap="round"/>
  </symbol>
</defs>
SVG;
    }
}
