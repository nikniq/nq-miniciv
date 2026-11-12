<?php

namespace App\Game;

use App\Models\Empire;
use App\Models\Game;
use Illuminate\Support\Collection;

/**
 * Inline SVG diagrams for the sidebar: the technology tree laid out by tier
 * with prerequisite links, and the web of diplomatic relations between
 * every pair of empires.
 */
final class DiagramSvg
{
    private const NODE_W = 120;

    private const NODE_H = 38;

    private const COL_GAP = 46;

    private const ROW_GAP = 10;

    /**
     * @param  array<int, array<int, string>>  $tiers  tier => tech keys
     * @param  array<string, mixed>  $available  techs the player could research now
     */
    public static function techTree(Empire $player, array $tiers, array $available, bool $interactive): string
    {
        $w = self::NODE_W;
        $h = self::NODE_H;
        $rows = max(array_map('count', $tiers));
        $width = count($tiers) * ($w + self::COL_GAP) - self::COL_GAP + 8;
        $height = $rows * ($h + self::ROW_GAP) - self::ROW_GAP + 8;

        // Positions, centring each column vertically.
        $pos = [];
        foreach ($tiers as $tier => $keys) {
            $colH = count($keys) * ($h + self::ROW_GAP) - self::ROW_GAP;
            $top = 4 + ($height - 8 - $colH) / 2;
            foreach ($keys as $i => $key) {
                $pos[$key] = [4 + $tier * ($w + self::COL_GAP), $top + $i * ($h + self::ROW_GAP)];
            }
        }

        $out = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" width="'.$width.'" height="'.$height.'" class="block" role="img" aria-label="Technology tree" font-family="ui-sans-serif, system-ui, sans-serif">'];
        $out[] = '<defs><marker id="tt-arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="6" markerHeight="6" orient="auto"><path d="M0 0 L8 4 L0 8 z" fill="#57534e"/></marker></defs>';

        // Links: from each prerequisite's right edge to the dependant's left edge.
        $out[] = '<g fill="none" stroke-width="1.5">';
        foreach (Rules::TECHS as $key => $t) {
            foreach ($t['requires'] as $req) {
                [$x1, $y1] = $pos[$req];
                [$x2, $y2] = $pos[$key];
                $sx = $x1 + $w;
                $sy = $y1 + $h / 2;
                $ex = $x2;
                $ey = $y2 + $h / 2;
                $mid = ($sx + $ex) / 2;
                $lit = $player->knows($req) && ($player->knows($key) || isset($available[$key]));
                $out[] = sprintf('<path d="M%s %s C%s %s %s %s %s %s" stroke="%s" opacity="%s" marker-end="url(#tt-arrow)"/>',
                    $sx, $sy, $mid, $sy, $mid, $ey, $ex, $ey, $lit ? '#34d399' : '#44403c', $lit ? '0.9' : '0.8');
            }
        }
        $out[] = '</g>';

        foreach ($pos as $key => [$x, $y]) {
            $t = Rules::TECHS[$key];
            $known = $player->knows($key);
            $avail = isset($available[$key]);
            $current = $player->current_tech === $key;
            [$fill, $stroke, $text, $sub] = match (true) {
                $known => ['#064e3b', '#10b981', '#d1fae5', '#6ee7b7'],
                $avail => ['#0c4a6e', '#38bdf8', '#e0f2fe', '#7dd3fc'],
                default => ['#1c1917', '#44403c', '#a8a29e', '#78716c'],
            };
            $title = e($t['name'].' ('.$t['cost'].' science): '.$t['unlocks'].($t['requires'] ? '. Needs '.implode(', ', array_map(fn ($r) => Rules::TECHS[$r]['name'], $t['requires'])) : ''));
            $clickable = $interactive && $avail && ! $current;
            $out[] = '<g'.($clickable ? ' data-research="'.$key.'" class="cursor-pointer tech-node"' : '').' tabindex="'.($clickable ? '0' : '-1').'">';
            $out[] = "<title>{$title}".($clickable ? ' — click to research' : '').'</title>';
            $out[] = sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="6" fill="%s" stroke="%s" stroke-width="%s"/>', $x, $y, $w, $h, $fill, $current ? '#fbbf24' : $stroke, $current ? 2.5 : 1.25);
            if ($current) {
                $pct = min(1, $player->research_progress / max(1, $t['cost']));
                $out[] = sprintf('<rect x="%s" y="%s" width="%s" height="3" rx="1.5" fill="#fbbf24"/>', $x + 6, $y + $h - 7, ($w - 12) * $pct);
            }
            $icon = $known ? '✓ ' : ($avail ? ($current ? '🔬 ' : '○ ') : '🔒 ');
            $out[] = sprintf('<text x="%s" y="%s" font-size="11" font-weight="600" fill="%s">%s%s</text>', $x + 8, $y + 15, $text, $icon, e($t['name']));
            $out[] = sprintf('<text x="%s" y="%s" font-size="9" fill="%s">%s</text>', $x + 8, $y + 28, $sub, e(mb_strimwidth($t['unlocks'], 0, 24, '…')).' · '.$t['cost'].'🔬');
            $out[] = '</g>';
        }
        $out[] = '</svg>';

        return implode("\n", $out);
    }

    /**
     * Every empire on a ring; a line per pair coloured by war, warmth or
     * coolness and weighted by how strongly they feel. The player sits at
     * the top and their lines carry the attitude number.
     *
     * @param  Collection<int, Empire>  $empires
     */
    public static function relations(Game $game, Collection $empires, Empire $player): string
    {
        $list = $empires->filter(fn ($e) => ! $e->eliminated)->sortBy(fn ($e) => $e->id === $player->id ? -1 : $e->id)->values();
        $n = $list->count();
        $size = 300;
        $cx = $size / 2;
        $cy = $size / 2 + 6;
        $r = $size / 2 - 46;
        $pos = [];
        foreach ($list as $i => $e) {
            $a = -M_PI / 2 + 2 * M_PI * $i / max(1, $n);
            $pos[$e->id] = [$cx + $r * cos($a), $cy + $r * sin($a)];
        }

        $out = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.($size + 10).'" class="block h-auto w-full" role="img" aria-label="Diplomatic relations" font-family="ui-sans-serif, system-ui, sans-serif">'];
        $out[] = '<g stroke-linecap="round">';
        foreach ($list as $i => $a) {
            foreach ($list->slice($i + 1) as $b) {
                $war = $game->atWar($a->id, $b->id);
                $att = $game->attitude($a->id, $b->id);
                $mine = $a->id === $player->id || $b->id === $player->id;
                [$x1, $y1] = $pos[$a->id];
                [$x2, $y2] = $pos[$b->id];
                if ($war) {
                    [$color, $width, $dash, $op] = ['#ef4444', 3.5, ' stroke-dasharray="7 5"', 0.95];
                } elseif ($game->allied($a->id, $b->id)) {
                    [$color, $width, $dash, $op] = ['#60a5fa', 5, '', 0.95];
                } elseif ($att > 15) {
                    [$color, $width, $dash, $op] = ['#34d399', 1.5 + min(4, $att / 20), '', $mine ? 0.95 : 0.6];
                } elseif ($att < -10) {
                    [$color, $width, $dash, $op] = ['#f97316', 1.5 + min(3, -$att / 20), ' stroke-dasharray="3 4"', $mine ? 0.9 : 0.55];
                } else {
                    [$color, $width, $dash, $op] = ['#57534e', 1.25, '', $mine ? 0.9 : 0.45];
                }
                $label = $war ? 'at war' : ($game->allied($a->id, $b->id) ? 'allies, ' : '').Rules::attitudeLabel($att)." ({$att})";
                $out[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="%s" opacity="%s"%s><title>%s and %s: %s</title></line>',
                    $x1, $y1, $x2, $y2, $color, $width, $op, $dash, e($a->name), e($b->name), $label);
                if ($mine) {
                    $mx = ($x1 + $x2) / 2;
                    $my = ($y1 + $y2) / 2;
                    $txt = $war ? '⚔' : ($game->allied($a->id, $b->id) ? '🤝' : ($att > 0 ? '+'.$att : (string) $att));
                    $out[] = sprintf('<rect x="%.1f" y="%.1f" width="%s" height="14" rx="7" fill="#0c0a09" stroke="%s" stroke-width="1"/><text x="%.1f" y="%.1f" font-size="9" text-anchor="middle" fill="%s">%s</text>',
                        $mx - 14, $my - 7, 28, $color, $mx, $my + 3.5, $color, $txt);
                }
            }
        }
        $out[] = '</g>';

        foreach ($list as $e) {
            [$x, $y] = $pos[$e->id];
            $me = $e->id === $player->id;
            $out[] = sprintf('<g><title>%s · army %s · %s</title><circle cx="%.1f" cy="%.1f" r="%s" fill="%s" stroke="%s" stroke-width="%s"/><text x="%.1f" y="%.1f" font-size="15" text-anchor="middle" dominant-baseline="central">%s</text><text x="%.1f" y="%.1f" font-size="10" font-weight="600" text-anchor="middle" fill="%s">%s</text></g>',
                e($e->name), $e->army, $me ? 'you' : Rules::personality($e->name)['trait'],
                $x, $y, $me ? 20 : 17, e($e->color), $me ? '#fde68a' : '#0c0a09', $me ? 3 : 2,
                $x, $y + 0.5, Rules::crest($e->name),
                $x, $y + ($y > $cy ? 34 : -25), $me ? '#fde68a' : '#e7e5e4', e($e->name));
        }
        $out[] = '</svg>';

        return implode("\n", $out);
    }
}
