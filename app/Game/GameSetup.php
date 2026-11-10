<?php

namespace App\Game;

use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new game: map, the player's empire, rival AI empires, and a
 * capital for each at well-spaced start sites.
 */
final class GameSetup
{
    public function __construct(private Actions $actions) {}

    public function create(User $user, string $empireName, int $rivals = 2, ?int $seed = null, string $size = 'normal', string $difficulty = 'prince'): Game
    {
        $seed ??= random_int(1, 2_000_000_000);
        $map = Rules::MAP_SIZES[$size] ?? Rules::MAP_SIZES['normal'];
        $difficulty = isset(Rules::DIFFICULTIES[$difficulty]) ? $difficulty : 'prince';
        $rivals = max(1, min($map['max_rivals'], $rivals));

        return DB::transaction(function () use ($user, $empireName, $rivals, $seed, $map, $difficulty) {
            $game = Game::create([
                'user_id' => $user->id,
                'name' => $empireName,
                'seed' => $seed,
                'width' => $map['width'],
                'height' => $map['height'],
                'max_turns' => Rules::MAX_TURNS,
                'difficulty' => $difficulty,
                'events' => ['Your people have founded their first city. Expand wisely.'],
                'history' => [],
            ]);

            $rows = (new MapGenerator($seed, $map['width'], $map['height']))->generate();
            $now = now();
            Tile::insert(array_map(fn ($t) => $t + ['game_id' => $game->id], $rows));
            $tiles = $game->tiles()->get();

            // Empires: player first, then rivals with distinct names.
            $names = Rules::EMPIRE_NAMES;
            mt_srand($seed + 7);
            shuffle($names);
            $names = array_values(array_filter($names, fn ($n) => strcasecmp($n, $empireName) !== 0));

            $empires = [];
            $empires[] = Empire::create([
                'game_id' => $game->id,
                'user_id' => $user->id,
                'name' => $empireName,
                'color' => Rules::COLORS[0],
                'is_ai' => false,
                'technologies' => [],
                'settlers' => 1,
                'gold' => 30,
            ]);
            for ($i = 0; $i < $rivals; $i++) {
                $empires[] = Empire::create([
                    'game_id' => $game->id,
                    'name' => $names[$i],
                    'color' => Rules::COLORS[$i + 1],
                    'is_ai' => true,
                    'technologies' => [],
                    'settlers' => 1,
                    'gold' => 30,
                ]);
            }

            $starts = $this->pickStarts($tiles, count($empires));
            foreach ($empires as $i => $empire) {
                $this->actions->foundCity($game, $empire, $starts[$i], $tiles, capital: true);
            }

            // Barbarian camps: a few, well away from every capital.
            mt_srand($seed + 99);
            $camps = 0;
            $candidates = $tiles->filter(function (Tile $t) use ($starts) {
                if (! $t->canSettle() || $t->empire_id) {
                    return false;
                }
                foreach ($starts as $c) {
                    if ($c->distanceTo($t) < 4) {
                        return false;
                    }
                }

                return true;
            })->shuffle();
            foreach ($candidates as $t) {
                if ($camps >= 3 + $rivals) {
                    break;
                }
                $t->camp = true;
                $t->save();
                $camps++;
            }

            return $game->fresh(['empires', 'cities', 'tiles']);
        });
    }

    /**
     * Start sites: settleable land with the best nearby food, chosen greedily
     * so each is at least 6 tiles from the others.
     */
    private function pickStarts($tiles, int $count): array
    {
        $grid = [];
        foreach ($tiles as $t) {
            $grid[$t->x.','.$t->y] = $t;
        }

        $scored = [];
        foreach ($tiles as $t) {
            if (! $t->canSettle()) {
                continue;
            }
            $score = 0;
            $landNeighbours = 0;
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    $n = $grid[($t->x + $dx).','.($t->y + $dy)] ?? null;
                    if (! $n) {
                        continue;
                    }
                    $y = Rules::tileYield($n);
                    $score += $y['food'] * 2 + $y['prod'] + $y['gold'];
                    $landNeighbours += $n->isLand() && $n->canSettle() ? 1 : 0;
                }
            }
            if ($landNeighbours < 4) {
                continue;
            }
            $scored[] = [$score, $t];
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);

        $chosen = [];
        foreach ([6, 5, 4, 3] as $minDist) {
            $chosen = [];
            foreach ($scored as [, $t]) {
                $ok = true;
                foreach ($chosen as $c) {
                    if ($t->distanceTo($c) < $minDist) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $chosen[] = $t;
                }
                if (count($chosen) === $count) {
                    return $chosen;
                }
            }
        }

        // Tiny or unlucky map: fall back to whatever is settleable.
        while (count($chosen) < $count && $scored) {
            $chosen[] = array_shift($scored)[1];
        }

        return $chosen;
    }
}
