<?php

namespace App\Game;

use App\Models\Empire;
use App\Models\Tile;

/**
 * Static game data: terrain, resources, buildings, technologies, and the
 * yield formula that ties them together. No state lives here.
 */
final class Rules
{
    public const MAP_WIDTH = 20;

    public const MAP_HEIGHT = 14;

    public const MAX_TURNS = 120;

    public const DOMINATION_SHARE = 0.55;

    public const MIN_CITY_DISTANCE = 3;

    public const SETTLER_COST = 50;

    /** Gold per remaining hammer when rush-buying. */
    public const BUY_RATE = 3;

    public const TERRAIN = [
        'ocean' => ['name' => 'Ocean', 'food' => 1, 'prod' => 0, 'gold' => 1, 'land' => false, 'settle' => false, 'color' => '#1e3a8a'],
        'grassland' => ['name' => 'Grassland', 'food' => 2, 'prod' => 0, 'gold' => 0, 'land' => true, 'settle' => true, 'color' => '#4d7c0f'],
        'plains' => ['name' => 'Plains', 'food' => 1, 'prod' => 1, 'gold' => 0, 'land' => true, 'settle' => true, 'color' => '#a16207'],
        'forest' => ['name' => 'Forest', 'food' => 1, 'prod' => 2, 'gold' => 0, 'land' => true, 'settle' => true, 'color' => '#14532d'],
        'hills' => ['name' => 'Hills', 'food' => 0, 'prod' => 2, 'gold' => 0, 'land' => true, 'settle' => true, 'color' => '#78716c'],
        'desert' => ['name' => 'Desert', 'food' => 0, 'prod' => 0, 'gold' => 1, 'land' => true, 'settle' => true, 'color' => '#d6b370'],
        'mountain' => ['name' => 'Mountain', 'food' => 0, 'prod' => 0, 'gold' => 0, 'land' => true, 'settle' => false, 'color' => '#44403c'],
    ];

    public const RESOURCES = [
        'wheat' => ['name' => 'Wheat', 'icon' => '🌾', 'food' => 2, 'prod' => 0, 'gold' => 0, 'science' => 0],
        'cattle' => ['name' => 'Cattle', 'icon' => '🐄', 'food' => 1, 'prod' => 1, 'gold' => 0, 'science' => 0],
        'horses' => ['name' => 'Horses', 'icon' => '🐎', 'food' => 0, 'prod' => 1, 'gold' => 1, 'science' => 0],
        'iron' => ['name' => 'Iron', 'icon' => '⛏️', 'food' => 0, 'prod' => 2, 'gold' => 0, 'science' => 0],
        'gold' => ['name' => 'Gold', 'icon' => '💰', 'food' => 0, 'prod' => 0, 'gold' => 3, 'science' => 0],
        'gems' => ['name' => 'Gems', 'icon' => '💎', 'food' => 0, 'prod' => 0, 'gold' => 2, 'science' => 1],
        'fish' => ['name' => 'Fish', 'icon' => '🐟', 'food' => 2, 'prod' => 0, 'gold' => 0, 'science' => 0],
    ];

    /**
     * Buildings. `effects` apply to the city that owns them; wonders also
     * carry `empire_effects` applied to every city of the owning empire.
     * Each wonder can be built once per game.
     */
    public const BUILDINGS = [
        'monument' => ['name' => 'Monument', 'cost' => 30, 'tech' => null, 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['culture' => 2, 'happy' => 1], 'blurb' => 'Expands borders faster, +1 happiness.'],
        'granary' => ['name' => 'Granary', 'cost' => 40, 'tech' => 'agriculture', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['food' => 2], 'blurb' => 'Faster growth.'],
        'workshop' => ['name' => 'Workshop', 'cost' => 60, 'tech' => 'mining', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['prod' => 2], 'blurb' => 'More production.'],
        'library' => ['name' => 'Library', 'cost' => 60, 'tech' => 'writing', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['science' => 3], 'blurb' => 'Research faster.'],
        'market' => ['name' => 'Market', 'cost' => 60, 'tech' => 'currency', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['gold' => 3], 'blurb' => 'Earn gold.'],
        'aqueduct' => ['name' => 'Aqueduct', 'cost' => 80, 'tech' => 'mathematics', 'requires' => null, 'wonder' => false, 'score' => 3,
            'effects' => ['food' => 3], 'blurb' => 'Large cities.'],
        'temple' => ['name' => 'Temple', 'cost' => 70, 'tech' => 'philosophy', 'requires' => 'monument', 'wonder' => false, 'score' => 3,
            'effects' => ['culture' => 3, 'science' => 1, 'happy' => 2], 'blurb' => 'Culture, +2 happiness, a little science.'],
        'university' => ['name' => 'University', 'cost' => 120, 'tech' => 'education', 'requires' => 'library', 'wonder' => false, 'score' => 4,
            'effects' => ['science' => 5], 'blurb' => 'Much faster research.'],
        'walls' => ['name' => 'Walls', 'cost' => 50, 'tech' => 'masonry', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => [], 'defense' => 6, 'blurb' => '+6 defence against attack.'],
        'bank' => ['name' => 'Bank', 'cost' => 120, 'tech' => 'banking', 'requires' => 'market', 'wonder' => false, 'score' => 4,
            'effects' => ['gold' => 5], 'blurb' => 'A great deal of gold.'],

        'pyramids' => ['name' => 'Pyramids', 'cost' => 200, 'tech' => 'masonry', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['prod' => 2], 'blurb' => 'Wonder: +2 production in every city.'],
        'hanging_gardens' => ['name' => 'Hanging Gardens', 'cost' => 220, 'tech' => 'mathematics', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['food' => 2, 'happy' => 1], 'blurb' => 'Wonder: +2 food and +1 happiness in every city.'],
        'great_library' => ['name' => 'Great Library', 'cost' => 220, 'tech' => 'philosophy', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['science' => 3], 'blurb' => 'Wonder: +3 science in every city.'],
        'colossus' => ['name' => 'Colossus', 'cost' => 180, 'tech' => 'currency', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['gold' => 3], 'blurb' => 'Wonder: +3 gold in every city.'],
    ];

    /**
     * Military units. Completed units add `strength` to the empire's army,
     * an abstract pool used to attack and defend cities.
     */
    public const UNITS = [
        'warriors' => ['name' => 'Warriors', 'cost' => 30, 'tech' => null, 'strength' => 2, 'blurb' => '+2 army strength.'],
        'swordsmen' => ['name' => 'Swordsmen', 'cost' => 50, 'tech' => 'bronze_working', 'strength' => 4, 'blurb' => '+4 army strength.'],
        'catapults' => ['name' => 'Catapults', 'cost' => 80, 'tech' => 'engineering', 'strength' => 7, 'blurb' => '+7 army strength.'],
    ];

    /** Happiness every empire gets for free, and extra per city. */
    public const BASE_HAPPINESS = 4;

    public const CITY_HAPPINESS = 2;

    /** Citizens per city who are content without any help. */
    public const FREE_CITIZENS = 2;

    /** Non-wonder buildings supported per gold of upkeep. */
    public const BUILDINGS_PER_GOLD = 2;

    /** Army strength supported per gold of upkeep. */
    public const ARMY_PER_GOLD = 4;

    /** Non-building things a city can produce. */
    public const PROJECTS = [
        'settlers' => ['name' => 'Settlers', 'cost' => self::SETTLER_COST, 'blurb' => 'Found a new city. Costs 1 population when complete; city must be size 2+.'],
        'wealth' => ['name' => 'Wealth', 'cost' => null, 'blurb' => 'Convert production into gold each turn.'],
    ];

    /** Technologies. `bonus` is a terrain yield bonus: [terrain => [yield => amount]]. */
    public const TECHS = [
        'agriculture' => ['name' => 'Agriculture', 'cost' => 20, 'requires' => [], 'bonus' => [], 'unlocks' => 'Granary'],
        'mining' => ['name' => 'Mining', 'cost' => 25, 'requires' => [], 'bonus' => [], 'unlocks' => 'Workshop'],
        'irrigation' => ['name' => 'Irrigation', 'cost' => 60, 'requires' => ['agriculture'], 'bonus' => ['grassland' => ['food' => 1]], 'unlocks' => '+1 food on grassland'],
        'writing' => ['name' => 'Writing', 'cost' => 45, 'requires' => ['agriculture'], 'bonus' => [], 'unlocks' => 'Library'],
        'bronze_working' => ['name' => 'Bronze Working', 'cost' => 45, 'requires' => ['mining'], 'bonus' => ['forest' => ['prod' => 1]], 'unlocks' => '+1 production in forests'],
        'masonry' => ['name' => 'Masonry', 'cost' => 50, 'requires' => ['mining'], 'bonus' => [], 'unlocks' => 'Pyramids'],
        'currency' => ['name' => 'Currency', 'cost' => 80, 'requires' => ['writing', 'bronze_working'], 'bonus' => [], 'unlocks' => 'Market, Colossus'],
        'mathematics' => ['name' => 'Mathematics', 'cost' => 90, 'requires' => ['writing', 'masonry'], 'bonus' => [], 'unlocks' => 'Aqueduct, Hanging Gardens'],
        'philosophy' => ['name' => 'Philosophy', 'cost' => 100, 'requires' => ['writing'], 'bonus' => [], 'unlocks' => 'Temple, Great Library'],
        'engineering' => ['name' => 'Engineering', 'cost' => 120, 'requires' => ['mathematics'], 'bonus' => ['hills' => ['prod' => 1]], 'unlocks' => '+1 production on hills'],
        'sailing' => ['name' => 'Sailing', 'cost' => 70, 'requires' => ['agriculture'], 'bonus' => ['ocean' => ['food' => 1]], 'unlocks' => '+1 food at sea'],
        'education' => ['name' => 'Education', 'cost' => 160, 'requires' => ['philosophy', 'mathematics'], 'bonus' => [], 'unlocks' => 'University'],
        'banking' => ['name' => 'Banking', 'cost' => 180, 'requires' => ['currency', 'education'], 'bonus' => [], 'unlocks' => 'Bank'],
    ];

    public const EMPIRE_NAMES = ['Rome', 'Egypt', 'Babylon', 'Carthage', 'Persia', 'Athens', 'Maurya', 'Han'];

    public const CITY_NAMES = [
        'Rome' => ['Roma', 'Antium', 'Cumae', 'Neapolis', 'Ravenna', 'Arretium', 'Mediolanum'],
        'Egypt' => ['Thebes', 'Memphis', 'Heliopolis', 'Elephantine', 'Alexandria', 'Pi-Ramesses'],
        'Babylon' => ['Babylon', 'Ur', 'Nippur', 'Akkad', 'Eridu', 'Lagash'],
        'Carthage' => ['Carthage', 'Utica', 'Hippo', 'Leptis', 'Gades', 'Saguntum'],
        'Persia' => ['Persepolis', 'Pasargadae', 'Susa', 'Ecbatana', 'Tarsus', 'Gordium'],
        'Athens' => ['Athens', 'Sparta', 'Corinth', 'Argos', 'Thebes', 'Delphi'],
        'Maurya' => ['Pataliputra', 'Taxila', 'Ujjain', 'Mathura', 'Kausambi'],
        'Han' => ['Chang\'an', 'Luoyang', 'Chengdu', 'Nanjing', 'Guangzhou'],
    ];

    public const COLORS = ['#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#22c55e'];

    /** Border radius (squared Euclidean) unlocked at each culture level. */
    public const BORDER_LEVELS = [0 => 2, 15 => 5, 50 => 10];

    /**
     * Yield of a single tile for a given empire: terrain + resource + tech
     * bonuses. Returns food, prod, gold, science.
     */
    public static function tileYield(Tile $tile, ?Empire $empire = null): array
    {
        $t = self::TERRAIN[$tile->terrain];
        $y = ['food' => $t['food'], 'prod' => $t['prod'], 'gold' => $t['gold'], 'science' => 0];

        if ($tile->resource) {
            foreach (self::RESOURCES[$tile->resource] as $k => $v) {
                if (isset($y[$k])) {
                    $y[$k] += $v;
                }
            }
        }

        if ($empire) {
            foreach ($empire->technologies ?? [] as $tech) {
                foreach (self::TECHS[$tech]['bonus'][$tile->terrain] ?? [] as $k => $v) {
                    $y[$k] += $v;
                }
            }
        }

        return $y;
    }

    public static function borderRadiusSq(int $culture): int
    {
        $r = 2;
        foreach (self::BORDER_LEVELS as $level => $radius) {
            if ($culture >= $level) {
                $r = $radius;
            }
        }

        return $r;
    }

    /** How hard a city is to take. */
    public static function cityDefense(\App\Models\City $city, int $garrison): int
    {
        $d = 5 + $city->population + $garrison;
        if ($city->is_capital) {
            $d += 4;
        }
        foreach ($city->buildings ?? [] as $b) {
            $d += self::BUILDINGS[$b]['defense'] ?? 0;
        }

        return $d;
    }

    public static function isWonder(string $key): bool
    {
        return self::BUILDINGS[$key]['wonder'] ?? false;
    }
}
