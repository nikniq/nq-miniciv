<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;

/**
 * A little skyline for the city panel: houses for population, a silhouette
 * per building, wonders standing tall at the back, walls in front. Pure
 * SVG shapes, no images, so it costs nothing to serve.
 */
final class CitySvg
{
    private const W = 300;

    private const H = 96;

    private const GROUND = 78;

    public static function render(City $city, Empire $empire): string
    {
        $w = self::W;
        $h = self::H;
        $g = self::GROUND;
        $color = e($empire->color);
        $out = [];
        $out[] = '<svg viewBox="0 0 '.$w.' '.$h.'" class="block h-auto w-full rounded border border-stone-800" role="img" aria-label="'.e($city->name).' skyline">';
        $out[] = '<defs><linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1c2541"/><stop offset="1" stop-color="#3a3f5c"/></linearGradient></defs>';
        $out[] = '<rect width="'.$w.'" height="'.$h.'" fill="url(#sky)"/>';
        // stars
        for ($i = 0; $i < 10; $i++) {
            $sx = (($i * 53 + $city->id * 7) % ($w - 10)) + 5;
            $sy = (($i * 29 + $city->id * 3) % 30) + 4;
            $out[] = sprintf('<circle cx="%d" cy="%d" r="0.8" fill="#fff" opacity="0.6"/>', $sx, $sy);
        }
        // moon
        $out[] = '<circle cx="'.($w - 30).'" cy="18" r="8" fill="#fde68a" opacity="0.85"/><circle cx="'.($w - 26).'" cy="16" r="7" fill="#1c2541" opacity="0.9"/>';
        // ground
        $out[] = sprintf('<rect x="0" y="%d" width="%d" height="%d" fill="#2d3a1f"/><rect x="0" y="%d" width="%d" height="2" fill="#4d7c0f" opacity="0.6"/>', $g, $w, $h - $g, $g, $w);

        $buildings = $city->buildings ?? [];
        $wonders = array_values(array_filter($buildings, fn ($b) => Rules::isWonder($b)));
        $ordinary = array_values(array_filter($buildings, fn ($b) => ! Rules::isWonder($b) && $b !== 'walls'));

        // Back row: wonders, centred.
        $wx = $w / 2 - (count($wonders) * 46) / 2 + 23;
        foreach ($wonders as $key) {
            $out[] = self::wonder($key, $wx, $g);
            $wx += 46;
        }

        // Middle row: houses for population, spread across.
        $houses = min(14, max(1, $city->population));
        $step = ($w - 20) / max(1, $houses);
        for ($i = 0; $i < $houses; $i++) {
            $hx = 10 + $i * $step + ($i % 2) * 3;
            $hh = 10 + (($i * 7 + $city->id) % 3) * 3;
            $out[] = sprintf('<rect x="%.1f" y="%d" width="10" height="%d" fill="#78716c"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="%s"/><rect x="%.1f" y="%d" width="2" height="2" fill="#fde68a"/>',
                $hx, $g - $hh, $hh, $hx - 1, $g - $hh, $hx + 5, $g - $hh - 6, $hx + 11, $g - $hh, $color, $hx + 4, $g - $hh + 3);
        }

        // Front row: ordinary buildings, left to right, each a distinct glyph.
        $bx = 14;
        $gap = min(34, ($w - 28) / max(1, count($ordinary)));
        foreach ($ordinary as $key) {
            $out[] = self::building($key, $bx, $g, $color);
            $bx += $gap;
        }

        // Walls across the very front.
        if (in_array('walls', $buildings, true)) {
            $out[] = sprintf('<rect x="0" y="%d" width="%d" height="7" fill="#a8a29e"/>', $g - 4, $w);
            for ($x = 0; $x < $w; $x += 10) {
                $out[] = sprintf('<rect x="%d" y="%d" width="5" height="3" fill="#a8a29e"/>', $x, $g - 7);
            }
        }

        $out[] = sprintf('<text x="6" y="%d" font-size="8" fill="#e7e5e4" opacity="0.8">%s · size %d</text>', $h - 4, e($city->name), $city->population);
        $out[] = '</svg>';

        return implode('', $out);
    }

    private static function building(string $key, float $x, int $g, string $color): string
    {
        return match ($key) {
            'monument' => sprintf('<rect x="%.1f" y="%d" width="4" height="22" fill="#d6d3d1"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#fafaf9"/><rect x="%.1f" y="%d" width="8" height="3" fill="#a8a29e"/>', $x + 2, $g - 22, $x + 2, $g - 22, $x + 4, $g - 27, $x + 6, $g - 22, $x, $g - 3),
            'granary' => sprintf('<rect x="%.1f" y="%d" width="12" height="16" rx="2" fill="#b45309"/><ellipse cx="%.1f" cy="%d" rx="6" ry="3" fill="#d97706"/>', $x, $g - 16, $x + 6, $g - 16),
            'workshop' => sprintf('<rect x="%.1f" y="%d" width="16" height="12" fill="#57534e"/><rect x="%.1f" y="%d" width="3" height="10" fill="#44403c"/><circle cx="%.1f" cy="%d" r="2" fill="#9ca3af" opacity="0.7"/><circle cx="%.1f" cy="%d" r="1.5" fill="#9ca3af" opacity="0.5"/>', $x, $g - 12, $x + 11, $g - 20, $x + 12.5, $g - 23, $x + 14, $g - 26),
            'library' => sprintf('<rect x="%.1f" y="%d" width="16" height="12" fill="#e7e5e4"/><rect x="%.1f" y="%d" width="2" height="12" fill="#a8a29e"/><rect x="%.1f" y="%d" width="2" height="12" fill="#a8a29e"/><rect x="%.1f" y="%d" width="2" height="12" fill="#a8a29e"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#f5f5f4"/>', $x, $g - 12, $x + 2, $g - 12, $x + 7, $g - 12, $x + 12, $g - 12, $x - 1, $g - 12, $x + 8, $g - 18, $x + 17, $g - 12),
            'market' => sprintf('<rect x="%.1f" y="%d" width="14" height="8" fill="#a16207"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="%s"/><rect x="%.1f" y="%d" width="2" height="4" fill="#fde68a"/><rect x="%.1f" y="%d" width="2" height="4" fill="#fde68a"/>', $x, $g - 8, $x - 2, $g - 8, $x + 7, $g - 14, $x + 16, $g - 8, $color, $x + 3, $g - 6, $x + 9, $g - 6),
            'aqueduct' => sprintf('<rect x="%.1f" y="%d" width="22" height="4" fill="#d6d3d1"/><path d="M%.1f %d v-10 a4 4 0 0 1 8 0 v10 M%.1f %d v-10 a4 4 0 0 1 8 0 v10" fill="none" stroke="#d6d3d1" stroke-width="3"/>', $x, $g - 16, $x + 1, $g, $x + 12, $g),
            'temple' => sprintf('<rect x="%.1f" y="%d" width="14" height="10" fill="#f5f5f4"/><rect x="%.1f" y="%d" width="2" height="10" fill="#a8a29e"/><rect x="%.1f" y="%d" width="2" height="10" fill="#a8a29e"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="%s"/>', $x, $g - 10, $x + 3, $g - 10, $x + 9, $g - 10, $x - 1, $g - 10, $x + 7, $g - 18, $x + 15, $g - 10, $color),
            'university' => sprintf('<rect x="%.1f" y="%d" width="18" height="12" fill="#e7e5e4"/><path d="M%.1f %d a9 7 0 0 1 18 0 z" fill="#0ea5e9"/><rect x="%.1f" y="%d" width="2" height="12" fill="#a8a29e"/><rect x="%.1f" y="%d" width="2" height="12" fill="#a8a29e"/>', $x, $g - 12, $x, $g - 12, $x + 4, $g - 12, $x + 12, $g - 12),
            'bank' => sprintf('<rect x="%.1f" y="%d" width="16" height="12" fill="#fef3c7"/><rect x="%.1f" y="%d" width="16" height="3" fill="#ca8a04"/><text x="%.1f" y="%d" font-size="8" font-weight="700" fill="#ca8a04">$</text>', $x, $g - 12, $x, $g - 15, $x + 5, $g - 3),
            'harbor' => sprintf('<rect x="%.1f" y="%d" width="18" height="3" fill="#7c2d12"/><path d="M%.1f %d l10 0 l-3 4 l-5 0 z" fill="#78350f"/><rect x="%.1f" y="%d" width="1.5" height="10" fill="#f5f5f4"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#f5f5f4"/>', $x, $g - 3, $x + 2, $g - 3, $x + 7, $g - 13, $x + 8.5, $g - 13, $x + 8.5, $g - 6, $x + 14, $g - 8),
            'colosseum' => sprintf('<ellipse cx="%.1f" cy="%d" rx="12" ry="5" fill="#d6d3d1"/><rect x="%.1f" y="%d" width="24" height="10" fill="#d6d3d1"/><ellipse cx="%.1f" cy="%d" rx="12" ry="5" fill="#a8a29e"/><ellipse cx="%.1f" cy="%d" rx="7" ry="2.5" fill="#57534e"/>', $x + 12, $g - 10, $x, $g - 10, $x + 12, $g, $x + 12, $g - 10),
            'courthouse' => sprintf('<rect x="%.1f" y="%d" width="16" height="11" fill="#f5f5f4"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#e7e5e4"/><rect x="%.1f" y="%d" width="2" height="11" fill="#a8a29e"/><rect x="%.1f" y="%d" width="2" height="11" fill="#a8a29e"/><rect x="%.1f" y="%d" width="2" height="11" fill="#a8a29e"/>', $x, $g - 11, $x - 1, $g - 11, $x + 8, $g - 17, $x + 17, $g - 11, $x + 2, $g - 11, $x + 7, $g - 11, $x + 12, $g - 11),
            'farms' => sprintf('<rect x="%.1f" y="%d" width="18" height="6" fill="#65a30d"/><path d="M%.1f %d h18 M%.1f %d h18 M%.1f %d h18" stroke="#3f6212" stroke-width="0.8"/><rect x="%.1f" y="%d" width="5" height="7" fill="#b45309"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#7c2d12"/>', $x, $g - 6, $x, $g - 4.5, $x, $g - 3, $x, $g - 1.5, $x + 13, $g - 13, $x + 12, $g - 13, $x + 15.5, $g - 17, $x + 19, $g - 13),
            'lumber_mill' => sprintf('<rect x="%.1f" y="%d" width="14" height="9" fill="#7c2d12"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#9a3412"/><circle cx="%.1f" cy="%d" r="5" fill="none" stroke="#d6d3d1" stroke-width="1.5"/><path d="M%.1f %d v10 M%.1f %d h10" stroke="#d6d3d1" stroke-width="1"/>', $x, $g - 9, $x - 1, $g - 9, $x + 7, $g - 14, $x + 15, $g - 9, $x + 17, $g - 12, $x + 17, $g - 17, $x + 12, $g - 12),
            'mine' => sprintf('<path d="M%.1f %d l9 -12 l9 12 z" fill="#57534e"/><path d="M%.1f %d a4 4 0 0 1 8 0 v4 h-8 z" fill="#1c1917"/><rect x="%.1f" y="%d" width="2" height="8" fill="#78716c" transform="rotate(35 %.1f %d)"/>', $x, $g, $x + 5, $g - 4, $x + 15, $g - 14, $x + 15, $g - 14),
            'castle' => sprintf('<rect x="%.1f" y="%d" width="18" height="14" fill="#78716c"/><rect x="%.1f" y="%d" width="4" height="18" fill="#57534e"/><rect x="%.1f" y="%d" width="4" height="18" fill="#57534e"/><path d="M%.1f %d h4 v-3 h-4 z M%.1f %d h4 v-3 h-4 z" fill="#57534e"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="%s"/>', $x, $g - 14, $x - 1, $g - 18, $x + 15, $g - 18, $x - 1, $g - 18, $x + 15, $g - 18, $x + 1, $g - 21, $x + 1, $g - 25, $x + 5, $g - 23, $color),
            'cathedral' => sprintf('<rect x="%.1f" y="%d" width="16" height="12" fill="#e7e5e4"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#a8a29e"/><rect x="%.1f" y="%d" width="4" height="22" fill="#d6d3d1"/><path d="M%.1f %d v-5 M%.1f %d h4" stroke="#fbbf24" stroke-width="1.2"/><path d="M%.1f %d a2 3 0 0 1 4 0 v4 h-4 z" fill="#60a5fa"/>', $x, $g - 12, $x - 1, $g - 12, $x + 8, $g - 18, $x + 17, $g - 12, $x + 1, $g - 22, $x + 3, $g - 22, $x + 1, $g - 25, $x + 9, $g - 8),
            'lighthouse' => sprintf('<polygon points="%.1f,%d %.1f,%d %.1f,%d %.1f,%d" fill="#f5f5f4"/><rect x="%.1f" y="%d" width="8" height="3" fill="#ef4444"/><rect x="%.1f" y="%d" width="8" height="3" fill="#ef4444"/><circle cx="%.1f" cy="%d" r="2.5" fill="#fde047"/>', $x + 2, $g, $x + 4, $g - 22, $x + 8, $g - 22, $x + 10, $g, $x + 2, $g - 8, $x + 3, $g - 16, $x + 6, $g - 24),
            'printing_press' => sprintf('<rect x="%.1f" y="%d" width="16" height="11" fill="#44403c"/><rect x="%.1f" y="%d" width="10" height="4" fill="#f5f5f4"/><rect x="%.1f" y="%d" width="4" height="6" fill="#78716c"/><path d="M%.1f %d h6 M%.1f %d h6" stroke="#1c1917" stroke-width="0.8"/>', $x, $g - 11, $x + 3, $g - 7, $x + 6, $g - 17, $x + 5, $g - 5.5, $x + 5, $g - 4),
            'guild_hall' => sprintf('<rect x="%.1f" y="%d" width="18" height="13" fill="#fef3c7"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#b45309"/><rect x="%.1f" y="%d" width="3" height="13" fill="#d6d3d1"/><rect x="%.1f" y="%d" width="3" height="13" fill="#d6d3d1"/><rect x="%.1f" y="%d" width="3" height="13" fill="#d6d3d1"/><circle cx="%.1f" cy="%d" r="2" fill="#ca8a04"/>', $x, $g - 13, $x - 1, $g - 13, $x + 9, $g - 19, $x + 19, $g - 13, $x + 2, $g - 13, $x + 7.5, $g - 13, $x + 13, $g - 13, $x + 9, $g - 15),
            default => sprintf('<rect x="%.1f" y="%d" width="12" height="10" fill="#78716c"/>', $x, $g - 10),
        };
    }

    private static function wonder(string $key, float $cx, int $g): string
    {
        return match ($key) {
            'pyramids' => sprintf('<polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#d4a657"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#b8893f"/>', $cx - 22, $g, $cx, $g - 40, $cx + 22, $g, $cx, $g - 40, $cx + 22, $g, $cx + 4, $g),
            'hanging_gardens' => sprintf('<rect x="%.1f" y="%d" width="40" height="12" fill="#a8a29e"/><rect x="%.1f" y="%d" width="28" height="12" fill="#a8a29e"/><rect x="%.1f" y="%d" width="16" height="12" fill="#a8a29e"/><rect x="%.1f" y="%d" width="40" height="3" fill="#16a34a"/><rect x="%.1f" y="%d" width="28" height="3" fill="#16a34a"/><rect x="%.1f" y="%d" width="16" height="3" fill="#16a34a"/>', $cx - 20, $g - 12, $cx - 14, $g - 24, $cx - 8, $g - 36, $cx - 20, $g - 14, $cx - 14, $g - 26, $cx - 8, $g - 38),
            'great_library' => sprintf('<rect x="%.1f" y="%d" width="40" height="26" fill="#f5f5f4"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#e7e5e4"/>%s', $cx - 20, $g - 26, $cx - 23, $g - 26, $cx, $g - 38, $cx + 23, $g - 26, implode('', array_map(fn ($i) => sprintf('<rect x="%.1f" y="%d" width="3" height="26" fill="#a8a29e"/>', $cx - 18 + $i * 8, $g - 26), range(0, 4)))),
            'colossus' => sprintf('<rect x="%.1f" y="%d" width="14" height="6" fill="#78716c"/><rect x="%.1f" y="%d" width="6" height="26" fill="#ca8a04"/><circle cx="%.1f" cy="%d" r="4" fill="#ca8a04"/><rect x="%.1f" y="%d" width="3" height="14" fill="#ca8a04" transform="rotate(-30 %.1f %d)"/><circle cx="%.1f" cy="%d" r="2.5" fill="#fde68a"/>', $cx - 7, $g - 6, $cx - 3, $g - 32, $cx, $g - 36, $cx + 2, $g - 44, $cx + 2, $g - 30, $cx + 8, $g - 46),
            'stonehenge' => implode('', array_map(fn ($i) => sprintf('<rect x="%.1f" y="%d" width="5" height="18" fill="#9ca3af"/><rect x="%.1f" y="%d" width="5" height="18" fill="#9ca3af"/><rect x="%.1f" y="%d" width="14" height="4" fill="#9ca3af"/>', $cx - 20 + $i * 16, $g - 18, $cx - 11 + $i * 16, $g - 18, $cx - 21 + $i * 16, $g - 22), range(0, 2))),
            'oracle' => sprintf('<rect x="%.1f" y="%d" width="30" height="16" fill="#f5f5f4"/><path d="M%.1f %d a15 12 0 0 1 30 0 z" fill="#fbbf24"/>%s', $cx - 15, $g - 16, $cx - 15, $g - 16, implode('', array_map(fn ($i) => sprintf('<rect x="%.1f" y="%d" width="2.5" height="16" fill="#a8a29e"/>', $cx - 13 + $i * 7, $g - 16), range(0, 3)))),
            'great_wall' => sprintf('<path d="M%.1f %d q10 -14 20 -4 t20 -4 t20 6" fill="none" stroke="#a8a29e" stroke-width="7"/><path d="M%.1f %d q10 -14 20 -4 t20 -4 t20 6" fill="none" stroke="#d6d3d1" stroke-width="2" stroke-dasharray="3 3"/>', $cx - 30, $g - 10, $cx - 30, $g - 14),
            'notre_dame' => sprintf('<rect x="%.1f" y="%d" width="36" height="24" fill="#e7e5e4"/><rect x="%.1f" y="%d" width="9" height="38" fill="#d6d3d1"/><rect x="%.1f" y="%d" width="9" height="38" fill="#d6d3d1"/><circle cx="%.1f" cy="%d" r="5" fill="#60a5fa" stroke="#1e3a8a" stroke-width="1"/><path d="M%.1f %d a4 6 0 0 1 8 0 v8 h-8 z" fill="#1e293b"/>', $cx - 18, $g - 24, $cx - 18, $g - 38, $cx + 9, $g - 38, $cx, $g - 16, $cx - 4, $g - 8),
            'machu_picchu' => sprintf('<polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#4d7c0f"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#65a30d"/>%s', $cx - 26, $g, $cx - 6, $g - 40, $cx + 14, $g, $cx - 2, $g, $cx + 14, $g - 30, $cx + 28, $g, implode('', array_map(fn ($i) => sprintf('<rect x="%.1f" y="%d" width="%d" height="3" fill="#a8a29e"/>', $cx - 12 + $i * 2, $g - 8 - $i * 6, 14 - $i * 2), range(0, 4)))),
            'angkor_wat' => sprintf('<rect x="%.1f" y="%d" width="44" height="12" fill="#78716c"/>%s', $cx - 22, $g - 12, implode('', array_map(fn ($i) => sprintf('<polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#a8a29e" stroke="#44403c" stroke-width="0.8"/>', $cx - 18 + $i * 12, $g - 12, $cx - 12 + $i * 12, $g - 12 - ($i === 1 ? 30 : 20), $cx - 6 + $i * 12, $g - 12), range(0, 2)))),
            // Caravanserai: a domed gate with a camel waiting outside.
            'silk_road' => sprintf('<rect x="%.1f" y="%d" width="34" height="20" fill="#d6b370"/><path d="M%.1f %d a7 8 0 0 1 14 0 v12 h-14 z" fill="#78350f"/><path d="M%.1f %d a9 7 0 0 1 18 0 z" fill="#b45309"/><path d="M%.1f %d h10 m-8 0 v-6 q3 -5 6 0 v6 m2 -6 q2 -3 4 -1" fill="none" stroke="#a16207" stroke-width="2" stroke-linecap="round"/>',
                $cx - 17, $g - 20, $cx - 7, $g - 12, $cx - 9, $g - 20, $cx + 14, $g, ),
            // Temple of Artemis: a long colonnade under a low pediment.
            'temple_of_artemis' => sprintf('<rect x="%.1f" y="%d" width="42" height="4" fill="#e7e5e4"/>%s<polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#f5f5f4" stroke="#a8a29e" stroke-width="0.8"/>',
                $cx - 21, $g - 4, implode('', array_map(fn ($i) => sprintf('<rect x="%.1f" y="%d" width="3" height="20" fill="#f5f5f4"/>', $cx - 19 + $i * 6.3, $g - 24), range(0, 6))), $cx - 23, $g - 24, $cx, $g - 34, $cx + 23, $g - 24),
            // Art of War: a tiered pagoda flying a red banner.
            'art_of_war' => sprintf('<rect x="%.1f" y="%d" width="16" height="10" fill="#7f1d1d"/><polygon points="%.1f,%d %.1f,%d %.1f,%d %.1f,%d" fill="#292524"/><rect x="%.1f" y="%d" width="12" height="9" fill="#991b1b"/><polygon points="%.1f,%d %.1f,%d %.1f,%d %.1f,%d" fill="#292524"/><line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#d6d3d1" stroke-width="1.2"/><polygon points="%.1f,%d %.1f,%d %.1f,%d" fill="#ef4444"/>',
                $cx - 8, $g - 10, $cx - 13, $g - 10, $cx + 13, $g - 10, $cx + 8, $g - 15, $cx - 8, $g - 15, $cx - 6, $g - 24, $cx - 10, $g - 24, $cx + 10, $g - 24, $cx + 6, $g - 29, $cx - 6, $g - 29, $cx, $g - 29, $cx, $g - 44, $cx, $g - 44, $cx + 10, $g - 41, $cx, $g - 38),
            // Grand Expedition: a carrack under full sail.
            'grand_expedition' => sprintf('<path d="M%1$.1f %2$d h40 l-7 9 h-26 z" fill="#78350f"/><rect x="%3$.1f" y="%4$d" width="1.6" height="36" fill="#44403c"/>'
                .'<path d="M%5$.1f %6$d h22 q3 6 0 12 h-22 q2 -6 0 -12 z" fill="#f5f5f4" stroke="#a8a29e" stroke-width="0.6"/>'
                .'<path d="M%7$.1f %8$d h16 q2 5 0 10 h-16 q2 -5 0 -10 z" fill="#e7e5e4" stroke="#a8a29e" stroke-width="0.6"/>'
                .'<polygon points="%9$.1f,%10$d %11$.1f,%12$d %9$.1f,%13$d" fill="#ef4444"/>',
                $cx - 20, $g - 9, $cx - 0.8, $g - 45, $cx - 11, $g - 24, $cx - 8, $g - 38, $cx, $g - 45, $cx + 9, $g - 42, $g - 39),
            default => sprintf('<rect x="%.1f" y="%d" width="30" height="30" fill="#fbbf24"/>', $cx - 15, $g - 30),
        };
    }
}
