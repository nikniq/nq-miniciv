<?php

namespace App\Game;

use App\Models\Game;

/**
 * Awards achievements to the player's account based on the game state.
 * Returns the newly earned ones so the turn can announce them.
 */
final class Achievements
{
    /** @return array<int, array{id:string,name:string,icon:string,blurb:string}> */
    public static function check(Game $game): array
    {
        $user = $game->user;
        $player = $game->playerEmpire();
        if (! $user || ! $player) {
            return [];
        }
        $earned = $user->achievements ?? [];
        $new = [];
        $award = function (string $id) use (&$earned, &$new, $game) {
            if (isset($earned[$id])) {
                return;
            }
            $earned[$id] = ['turn' => $game->turn, 'game' => $game->id, 'at' => now()->toDateString()];
            $new[] = ['id' => $id] + Rules::ACHIEVEMENTS[$id];
        };

        $cities = $player->cities->count();
        if ($cities >= 2) {
            $award('second_city');
        }
        if ($cities >= 5) {
            $award('five_cities');
        }
        if ($player->wonders()) {
            $award('first_wonder');
        }
        if ($player->explored !== null && Fog::percent($game, $player) >= 100) {
            $award('cartographer');
        }
        if ((int) $player->great_count > 0) {
            $award('great_person');
        }
        if ($player->golden_age_until > 0) {
            $award('golden_age');
        }
        if ($player->captures > 0) {
            $award('conqueror');
        }
        foreach ($game->diplomacy ?? [] as $rel) {
            if (($rel['state'] ?? '') === 'peace' && ($rel['since'] ?? 0) > 0) {
                $award('peacemaker');
                break;
            }
        }
        if (count($player->technologies ?? []) >= count(Rules::TECHS)) {
            $award('scholar');
        }
        if ($game->status === 'won') {
            $first = $game->events[0] ?? '';
            if (str_contains($first, 'Science victory')) {
                $award('win_science');
            } elseif (str_contains($first, 'dominates')) {
                $award('win_domination');
            } elseif (str_contains($first, 'Cultural')) {
                $award('win_culture');
            } else {
                $award('win_score');
            }
            if (in_array($game->difficulty, ['king', 'emperor'], true)) {
                $award('win_king');
            }
            if ($game->width >= Rules::MAP_SIZES['huge']['width']) {
                $award('win_huge');
            }
        }

        if ($new) {
            $user->achievements = $earned;
            $user->save();
        }

        return $new;
    }
}
