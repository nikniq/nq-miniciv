<?php

namespace App\Game;

use App\Models\City;
use App\Models\Empire;
use App\Models\Game;
use InvalidArgumentException;

/**
 * Decision events: now and then the player is handed a dilemma with two
 * answers. It waits on the game (games.choice) until answered or until the
 * next turn ends, when the second, safer option is taken by default.
 */
final class Choices
{
    public const CHANCE = 9; // percent per turn when nothing is pending

    public const KINDS = ['envoy', 'caravan', 'prophet', 'mercenaries', 'plague', 'refugees'];

    /** Draw a dilemma for the player, if the dice say so. Returns the pending choice or null. */
    public static function draw(Game $game, Empire $player, array $rivals): ?array
    {
        if ($game->choice || $player->cities->isEmpty()) {
            return null;
        }
        mt_srand($game->seed * 17 + $game->turn * 13);

        // A devoted rival may come asking for an alliance (a third of the time it could).
        foreach ($rivals as $r) {
            $others = $game->empires->filter(fn ($o) => ! $o->eliminated && $o->id !== $r->id && $o->id !== $player->id);
            $wouldCrown = $others->isNotEmpty() && $others->every(fn ($o) => $game->allied($r->id, $o->id));
            if (! $game->allied($player->id, $r->id) && ! $wouldCrown && $game->attitude($player->id, $r->id) >= Rules::ALLIANCE_ATTITUDE + 5 && mt_rand(1, 3) === 1) {
                return ['kind' => 'alliance', 'turn' => $game->turn, 'city' => $player->cities->first()->id, 'rival' => $r->id];
            }
        }

        if (mt_rand(1, 100) > self::CHANCE) {
            return null;
        }
        $kinds = self::KINDS;
        if (! $rivals) {
            $kinds = array_values(array_diff($kinds, ['envoy']));
        }
        $kind = $kinds[mt_rand(0, count($kinds) - 1)];
        $city = $player->cities->values()->get(mt_rand(0, $player->cities->count() - 1));
        if ($kind === 'plague' && ($city->population < 3 || $city->has('aqueduct'))) {
            $kind = 'caravan';
        }
        $rival = $rivals ? $rivals[mt_rand(0, count($rivals) - 1)] : null;

        return ['kind' => $kind, 'turn' => $game->turn, 'city' => $city->id, 'rival' => $rival?->id];
    }

    /** Text and options for a pending choice, for the view. */
    public static function describe(Game $game, array $choice): array
    {
        $city = $game->cities->firstWhere('id', $choice['city']);
        $cityName = $city?->name ?? 'your capital';
        $rival = $choice['rival'] ? $game->empires->firstWhere('id', $choice['rival']) : null;
        $rivalName = $rival?->name ?? 'a distant land';
        $gold = self::goldScale($game);

        return match ($choice['kind']) {
            'alliance' => ['icon' => '🤝', 'title' => "{$rivalName} proposes an alliance",
                'text' => 'Their ruler writes of shared fortunes: each would join any war declared on the other, and your scouts would share their maps.',
                'a' => 'Accept the alliance', 'b' => 'Decline politely (−3 attitude)'],
            'envoy' => ['icon' => '🕊️', 'title' => "An envoy from {$rivalName}",
                'text' => "An envoy arrives bearing compliments and asks for {$gold} gold as a token of lasting friendship.",
                'a' => "Pay {$gold} gold (+15 attitude)", 'b' => 'Send them home (−6 attitude)'],
            'caravan' => ['icon' => '🐪', 'title' => "A caravan reaches {$cityName}",
                'text' => 'Foreign merchants offer tools, timber and stone at a fair price.',
                'a' => "Buy for {$gold} gold (+".(2 * $gold).' production in '.$cityName.')', 'b' => 'Trade news instead (+8 science)'],
            'prophet' => ['icon' => '🔮', 'title' => "A prophet preaches in {$cityName}",
                'text' => 'Crowds gather. The priests are nervous; the merchants see a market.',
                'a' => 'Welcome the prophet (+'.(20 + $gold).' culture)', 'b' => "Tax the pilgrims (+{$gold} gold)"],
            'mercenaries' => ['icon' => '🗡️', 'title' => 'Mercenaries offer their swords',
                'text' => "A band of veterans camps outside {$cityName}, offering service for {$gold} gold.",
                'a' => "Hire them for {$gold} gold (+2 Warriors)", 'b' => 'Decline (+5 culture, the bards sing of your thrift)'],
            'plague' => ['icon' => '☣️', 'title' => "Sickness in {$cityName}",
                'text' => 'Fever spreads through the poorer quarters. The physicians ask you to burn the granaries.',
                'a' => 'Burn the stores (all stored food lost, no deaths)', 'b' => 'Trust to prayer (lose 1 population, +10 culture)'],
            'refugees' => ['icon' => '🏃', 'title' => "Refugees at the gates of {$cityName}",
                'text' => 'Families fleeing war ask for shelter. Feeding them will strain the city.',
                'a' => 'Take them in (+1 population, −'.(2 * $gold).' food stored)', 'b' => "Turn them away (+{$gold} gold from their valuables)"],
            default => ['icon' => '❔', 'title' => 'A decision', 'text' => '', 'a' => 'Yes', 'b' => 'No'],
        };
    }

    /** Apply the chosen option ('a' or 'b'). Returns a chronicle line. */
    public static function resolve(Game $game, Empire $player, string $option): string
    {
        $choice = $game->choice ?? throw new InvalidArgumentException('There is nothing to decide.');
        if (! in_array($option, ['a', 'b'], true)) {
            throw new InvalidArgumentException('Pick one of the two answers.');
        }
        /** @var City|null $city */
        $city = $player->cities->firstWhere('id', $choice['city']) ?? $player->cities->first();
        $rival = $choice['rival'] ? $game->empires->firstWhere('id', $choice['rival']) : null;
        $gold = self::goldScale($game);
        $a = $option === 'a';
        if ($a && in_array($choice['kind'], ['envoy', 'caravan', 'mercenaries'], true) && $player->gold < $gold) {
            throw new InvalidArgumentException("You need {$gold} gold for that.");
        }
        $line = match ($choice['kind']) {
            'alliance' => $a
                ? (function () use ($game, $player, $rival) {
                    if (! $rival || $rival->eliminated || $game->atWar($player->id, $rival->id)) {
                        return 'The offer has lapsed.';
                    }
                    $game->setAllied($player->id, $rival->id, true);
                    $game->adjustAttitude($player->id, $rival->id, 5);

                    return "🤝 You and {$rival->name} are now allies.";
                })()
                : (function () use ($game, $player, $rival) {
                    if ($rival) {
                        $game->adjustAttitude($player->id, $rival->id, -3);
                    }

                    return 'You decline the alliance. They take it with grace, mostly.';
                })(),
            'envoy' => $a
                ? (function () use ($player, $gold, $game, $rival) {
                    $player->gold -= $gold;
                    if ($rival) {
                        $game->adjustAttitude($player->id, $rival->id, 15);
                    }

                    return 'The envoy departs delighted; '.($rival?->name ?? 'their court').' thinks warmly of you.';
                })()
                : (function () use ($game, $player, $rival) {
                    if ($rival) {
                        $game->adjustAttitude($player->id, $rival->id, -6);
                    }

                    return 'The envoy leaves empty-handed and a little offended.';
                })(),
            'caravan' => $a
                ? (function () use ($player, $city, $gold) {
                    $player->gold -= $gold;
                    $city->production_stored += 2 * $gold;

                    return "Wagons of material fill the yards of {$city->name}.";
                })()
                : (function () use ($player) {
                    $player->research_progress += 8;

                    return 'The merchants share tales of far-off inventions.';
                })(),
            'prophet' => $a
                ? (function () use ($city, $gold) {
                    $city->culture += 20 + $gold;

                    return "The prophet's sermons become legend in {$city->name}.";
                })()
                : (function () use ($player, $gold) {
                    $player->gold += $gold;

                    return 'The pilgrims grumble but pay. The treasury swells.';
                })(),
            'mercenaries' => $a
                ? (function () use ($player, $gold) {
                    $player->gold -= $gold;
                    $player->addUnit('warriors');
                    $player->addUnit('warriors');

                    return 'The veterans swear their oath. Two bands of Warriors join the army.';
                })()
                : (function () use ($city) {
                    $city->culture += 5;

                    return 'The mercenaries move on. The bards approve.';
                })(),
            'plague' => $a
                ? (function () use ($city) {
                    $city->food_stored = 0;

                    return "The granaries of {$city->name} burn, and the fever burns out with them.";
                })()
                : (function () use ($city) {
                    $city->population = max(1, $city->population - 1);
                    $city->culture += 10;

                    return "Prayers rise over {$city->name}; the dead are mourned and remembered.";
                })(),
            'refugees' => $a
                ? (function () use ($city, $gold) {
                    $city->population += 1;
                    $city->food_stored = max(0, $city->food_stored - 2 * $gold);

                    return "{$city->name} takes in the refugees and grows.";
                })()
                : (function () use ($player, $gold) {
                    $player->gold += $gold;

                    return 'The gates stay shut. The guards keep what the refugees could not carry.';
                })(),
            default => 'Done.',
        };
        $city->save();
        $player->save();
        $game->choice = null;
        $game->save();

        return $line;
    }

    private static function goldScale(Game $game): int
    {
        return 20 + 5 * intdiv($game->turn, 10);
    }
}
