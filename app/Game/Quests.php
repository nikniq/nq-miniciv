<?php

namespace App\Game;

use App\Models\Empire;
use App\Models\Game;
use App\Models\Tile;
use Illuminate\Support\Collection;

/**
 * Favours rivals ask of the player: clear a camp that troubles them, send a
 * gift, open a trade route to them, or join their war. Each has a deadline;
 * keeping it earns trust (and gold for the harder ones), ignoring it costs a
 * little. Stored on games.quests.
 */
final class Quests
{
    public const CHANCE = 7; // percent per turn

    public const MAX_ACTIVE = 2;

    public const DURATION = 12;

    public const REWARD_ATTITUDE = 20;

    /** Maybe issue a new request. Returns an announcement or null. */
    public static function maybeIssue(Game $game, Empire $player, Collection $tiles): ?string
    {
        $quests = $game->quests ?? [];
        if ($game->turn < 10 || count($quests) >= self::MAX_ACTIVE) {
            return null;
        }
        mt_srand($game->seed * 13 + $game->turn * 31);
        if (mt_rand(1, 100) > self::CHANCE) {
            return null;
        }
        $askers = $game->empires->filter(fn ($e) => $e->is_ai && ! $e->eliminated && ! $game->atWar($player->id, $e->id)
            && $game->attitude($player->id, $e->id) > -40 && ! collect($quests)->contains('empire', $e->id))->values();
        if ($askers->isEmpty()) {
            return null;
        }
        $asker = $askers->get(mt_rand(0, $askers->count() - 1));
        $options = [];

        // A camp near one of their cities.
        $centres = $asker->cities->map(fn ($c) => $tiles->firstWhere('id', $c->tile_id))->filter();
        $camp = $tiles->first(fn (Tile $t) => $t->camp && $centres->contains(fn ($c) => $c->distanceTo($t) <= 5));
        if ($camp) {
            $options[] = ['kind' => 'camp', 'target' => $camp->id, 'amount' => 0];
        }
        $options[] = ['kind' => 'gold', 'target' => null, 'amount' => $game->turn >= 50 ? 100 : 50];
        if ($player->knows('currency') && $asker->cities->isNotEmpty()) {
            $options[] = ['kind' => 'route', 'target' => null, 'amount' => 0];
        }
        $enemy = $game->empires->first(fn ($e) => $e->id !== $player->id && $e->id !== $asker->id && ! $e->eliminated
            && $game->atWar($asker->id, $e->id) && ! $game->atWar($player->id, $e->id) && ! $game->allied($player->id, $e->id));
        if ($enemy) {
            $options[] = ['kind' => 'war', 'target' => $enemy->id, 'amount' => 0];
        }
        $q = $options[mt_rand(0, count($options) - 1)] + ['empire' => $asker->id, 'progress' => 0, 'until' => $game->turn + self::DURATION];
        $quests[] = $q;
        $game->quests = $quests;

        return '📜 '.$asker->name.' asks a favour: '.self::describe($game, $q, $tiles).' ('.self::DURATION.' turns).';
    }

    /** Plain-language request. */
    public static function describe(Game $game, array $q, Collection $tiles): string
    {
        return match ($q['kind']) {
            'camp' => (fn ($t) => 'destroy the barbarian camp at ('.($t?->x ?? '?').', '.($t?->y ?? '?').') that threatens them')($tiles->firstWhere('id', $q['target'])),
            'gold' => "send a gift of {$q['amount']} gold".($q['progress'] ? " ({$q['progress']} given)" : ''),
            'route' => 'open a trade route to one of their cities',
            'war' => 'join their war against '.($game->empires->firstWhere('id', $q['target'])?->name ?? 'their enemy'),
            default => 'a favour',
        };
    }

    /** Settle requests at the end of a turn. Returns events for the player. */
    public static function check(Game $game, Empire $player, Collection $tiles): array
    {
        $events = [];
        $keep = [];
        foreach ($game->quests ?? [] as $q) {
            $asker = $game->empires->firstWhere('id', $q['empire']);
            if (! $asker || $asker->eliminated || $game->atWar($player->id, $asker->id)) {
                continue; // moot
            }
            $done = match ($q['kind']) {
                'route' => $player->cities->contains(fn ($c) => $c->trade_to && $asker->cities->contains('id', $c->trade_to)),
                'war' => $game->atWar($player->id, $q['target']),
                default => $q['done'] ?? false,
            };
            if ($q['kind'] === 'camp' && ! $done && ! $tiles->firstWhere('id', $q['target'])?->camp) {
                $events[] = "📜 Someone else destroyed the camp {$asker->name} feared. The request lapses.";

                continue;
            }
            if ($done) {
                $events[] = self::reward($game, $player, $asker, $q);

                continue;
            }
            if ($game->turn >= $q['until']) {
                $game->adjustAttitude($player->id, $asker->id, -5);
                $events[] = "📜 You let {$asker->name} down: ".self::describe($game, $q, $tiles).' went undone.';

                continue;
            }
            $keep[] = $q;
        }
        $game->quests = $keep;

        return $events;
    }

    /** The player cleared a camp: mark any request for it as done. */
    public static function campCleared(Game $game, int $tileId): void
    {
        $game->quests = array_map(fn ($q) => $q['kind'] === 'camp' && $q['target'] === $tileId ? $q + ['done' => true] : $q, $game->quests ?? []);
        $game->save();
    }

    /** The player sent a gift: count it toward a gold request from that empire. */
    public static function giftSent(Game $game, int $empireId, int $gold): void
    {
        $game->quests = array_map(function ($q) use ($empireId, $gold) {
            if ($q['kind'] === 'gold' && $q['empire'] === $empireId) {
                $q['progress'] += $gold;
                $q['done'] = $q['progress'] >= $q['amount'];
            }

            return $q;
        }, $game->quests ?? []);
        $game->save();
    }

    private static function reward(Game $game, Empire $player, Empire $asker, array $q): string
    {
        $game->adjustAttitude($player->id, $asker->id, self::REWARD_ATTITUDE);
        $gold = $q['kind'] === 'gold' ? 0 : 30 + intdiv($game->turn, 2);
        if ($gold) {
            $player->gold += $gold;
            $player->save();
        }

        return "📜 {$asker->name} is grateful: favour kept (+".self::REWARD_ATTITUDE.' attitude'.($gold ? ", {$gold} gold" : '').').';
    }
}
