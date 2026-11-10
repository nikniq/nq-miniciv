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

    /** Map sizes: width, height, rivals allowed. */
    public const MAP_SIZES = [
        'small' => ['name' => 'Small', 'width' => 18, 'height' => 12, 'max_rivals' => 2],
        'normal' => ['name' => 'Normal', 'width' => 26, 'height' => 17, 'max_rivals' => 4],
        'large' => ['name' => 'Large', 'width' => 34, 'height' => 22, 'max_rivals' => 6],
        'huge' => ['name' => 'Huge', 'width' => 44, 'height' => 28, 'max_rivals' => 7],
    ];

    /** River bonus on land tiles; deserts by a river become flood plains. */
    public const RIVER_BONUS = ['food' => 1, 'gold' => 1];

    public const FLOOD_PLAIN_FOOD = 2;

    /** Difficulty: multipliers applied to AI production and science. */
    public const DIFFICULTIES = [
        'settler' => ['name' => 'Settler', 'ai' => 0.75, 'blurb' => 'Rivals build and research slowly.'],
        'prince' => ['name' => 'Prince', 'ai' => 1.0, 'blurb' => 'Even footing.'],
        'king' => ['name' => 'King', 'ai' => 1.25, 'blurb' => 'Rivals build and research faster.'],
        'emperor' => ['name' => 'Emperor', 'ai' => 1.5, 'blurb' => 'Rivals are formidable.'],
    ];

    public const DOMINATION_SHARE = 0.55;

    /** Total culture for a cultural victory on a normal map; scales with area. */
    public const CULTURE_VICTORY = 3000;

    public static function cultureGoal(\App\Models\Game $game): int
    {
        $normal = self::MAP_SIZES['normal']['width'] * self::MAP_SIZES['normal']['height'];

        return (int) round(self::CULTURE_VICTORY * max(1, ($game->width * $game->height) / $normal) / 100) * 100;
    }

    /** How many cities a rival aims for, by map area. */
    public static function aiCityCap(\App\Models\Game $game): int
    {
        return max(6, intdiv($game->width * $game->height, 70));
    }

    /** Turns a peace treaty must hold before war can be declared again. */
    public const PEACE_MIN_TURNS = 10;

    /** Happiness lost per empire you are at war with. */
    public const WAR_WEARINESS = 2;

    /** Gold per turn from each peaceful neighbour you share a border with (needs Currency). */
    public const TRADE_GOLD_PER_PARTNER = 3;

    /** Chance per empire per turn of a random event, in percent. */
    public const EVENT_CHANCE = 7;

    /** Army needed to clear a barbarian camp, and what it costs. */
    public const CAMP_CLEAR_ARMY = 4;

    public const CAMP_CLEAR_LOSS = 2;

    /** City focus options: weights for choosing worked tiles. */
    public const FOCUS = [
        'balanced' => ['name' => 'Balanced', 'food' => 2, 'prod' => 1.5, 'gold' => 1, 'science' => 1],
        'food' => ['name' => 'Growth', 'food' => 4, 'prod' => 1, 'gold' => 0.5, 'science' => 0.5],
        'prod' => ['name' => 'Production', 'food' => 1.2, 'prod' => 4, 'gold' => 0.5, 'science' => 0.5],
        'gold' => ['name' => 'Gold', 'food' => 1.2, 'prod' => 1, 'gold' => 4, 'science' => 1],
        'science' => ['name' => 'Science', 'food' => 1.2, 'prod' => 1, 'gold' => 1, 'science' => 4],
    ];

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
        'monument' => ['name' => 'Monument', 'icon' => '🗿', 'cost' => 30, 'tech' => null, 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['culture' => 2, 'happy' => 1], 'blurb' => 'Expands borders faster, +1 happiness.'],
        'granary' => ['name' => 'Granary', 'icon' => '🌾', 'cost' => 40, 'tech' => 'agriculture', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['food' => 2], 'blurb' => 'Faster growth.'],
        'workshop' => ['name' => 'Workshop', 'icon' => '⚒️', 'cost' => 60, 'tech' => 'mining', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['prod' => 2], 'blurb' => 'More production.'],
        'library' => ['name' => 'Library', 'icon' => '📚', 'cost' => 60, 'tech' => 'writing', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['science' => 3], 'blurb' => 'Research faster.'],
        'market' => ['name' => 'Market', 'icon' => '🏪', 'cost' => 60, 'tech' => 'currency', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => ['gold' => 3], 'blurb' => 'Earn gold.'],
        'aqueduct' => ['name' => 'Aqueduct', 'icon' => '🚰', 'cost' => 80, 'tech' => 'mathematics', 'requires' => null, 'wonder' => false, 'score' => 3,
            'effects' => ['food' => 3], 'blurb' => 'Large cities.'],
        'temple' => ['name' => 'Temple', 'icon' => '⛩️', 'cost' => 70, 'tech' => 'philosophy', 'requires' => 'monument', 'wonder' => false, 'score' => 3,
            'effects' => ['culture' => 3, 'science' => 1, 'happy' => 2], 'blurb' => 'Culture, +2 happiness, a little science.'],
        'university' => ['name' => 'University', 'icon' => '🎓', 'cost' => 120, 'tech' => 'education', 'requires' => 'library', 'wonder' => false, 'score' => 4,
            'effects' => ['science' => 5], 'blurb' => 'Much faster research.'],
        'walls' => ['name' => 'Walls', 'icon' => '🧱', 'cost' => 50, 'tech' => 'masonry', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => [], 'defense' => 6, 'blurb' => '+6 defence against attack.'],
        'harbor' => ['name' => 'Harbor', 'icon' => '⚓', 'cost' => 70, 'tech' => 'sailing', 'requires' => null, 'wonder' => false, 'score' => 2,
            'effects' => [], 'terrain_bonus' => ['ocean' => ['food' => 1, 'gold' => 1]], 'blurb' => '+1 food and +1 gold on this city\'s sea tiles.'],
        'colosseum' => ['name' => 'Colosseum', 'icon' => '🏟️', 'cost' => 90, 'tech' => 'construction', 'requires' => null, 'wonder' => false, 'score' => 3,
            'effects' => ['happy' => 3], 'blurb' => '+3 happiness.'],
        'courthouse' => ['name' => 'Courthouse', 'icon' => '⚖️', 'cost' => 90, 'tech' => 'code_of_laws', 'requires' => null, 'wonder' => false, 'score' => 3,
            'effects' => ['happy' => 2, 'gold' => 2], 'blurb' => '+2 happiness, +2 gold.'],
        'bank' => ['name' => 'Bank', 'icon' => '🏦', 'cost' => 120, 'tech' => 'banking', 'requires' => 'market', 'wonder' => false, 'score' => 4,
            'effects' => ['gold' => 5], 'blurb' => 'A great deal of gold.'],

        'pyramids' => ['name' => 'Pyramids', 'icon' => '🔺', 'cost' => 200, 'tech' => 'masonry', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['prod' => 2], 'blurb' => 'Wonder: +2 production in every city.'],
        'hanging_gardens' => ['name' => 'Hanging Gardens', 'icon' => '🌿', 'cost' => 220, 'tech' => 'mathematics', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['food' => 2, 'happy' => 1], 'blurb' => 'Wonder: +2 food and +1 happiness in every city.'],
        'great_library' => ['name' => 'Great Library', 'icon' => '📜', 'cost' => 220, 'tech' => 'philosophy', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['science' => 3], 'blurb' => 'Wonder: +3 science in every city.'],
        'stonehenge' => ['name' => 'Stonehenge', 'icon' => '🪨', 'cost' => 150, 'tech' => null, 'requires' => null, 'wonder' => true, 'score' => 20,
            'effects' => ['culture' => 6], 'empire_effects' => ['culture' => 1], 'blurb' => 'Wonder: +6 culture here, +1 culture in every city.'],
        'oracle' => ['name' => 'Oracle', 'icon' => '🔮', 'cost' => 180, 'tech' => 'philosophy', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3, 'science' => 3], 'empire_effects' => ['happy' => 1], 'blurb' => 'Wonder: +1 happiness in every city, +3 science here.'],
        'great_wall' => ['name' => 'Great Wall', 'icon' => '🏯', 'cost' => 200, 'tech' => 'construction', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['defense' => 4], 'blurb' => 'Wonder: +4 defence in every city.'],
        'colossus' => ['name' => 'Colossus', 'icon' => '🗽', 'cost' => 180, 'tech' => 'currency', 'requires' => null, 'wonder' => true, 'score' => 25,
            'effects' => ['culture' => 3], 'empire_effects' => ['gold' => 3], 'blurb' => 'Wonder: +3 gold in every city.'],
    ];

    /**
     * Military units. Completed units add `strength` to the empire's army,
     * an abstract pool used to attack and defend cities.
     */
    public const UNITS = [
        'warriors' => ['name' => 'Warriors', 'cost' => 30, 'tech' => null, 'strength' => 2, 'blurb' => '+2 army strength.'],
        'horsemen' => ['name' => 'Horsemen', 'cost' => 45, 'tech' => 'horseback_riding', 'strength' => 3, 'blurb' => '+3 army strength.'],
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
        'construction' => ['name' => 'Construction', 'cost' => 95, 'requires' => ['masonry', 'bronze_working'], 'bonus' => [], 'unlocks' => 'Colosseum, Great Wall'],
        'code_of_laws' => ['name' => 'Code of Laws', 'cost' => 85, 'requires' => ['writing'], 'bonus' => [], 'unlocks' => 'Courthouse'],
        'horseback_riding' => ['name' => 'Horseback Riding', 'cost' => 60, 'requires' => ['agriculture'], 'bonus' => ['plains' => ['food' => 1]], 'unlocks' => 'Horsemen, +1 food on plains'],
        'astronomy' => ['name' => 'Astronomy', 'cost' => 130, 'requires' => ['mathematics', 'sailing'], 'bonus' => ['ocean' => ['gold' => 1]], 'unlocks' => '+1 gold at sea'],
        'education' => ['name' => 'Education', 'cost' => 160, 'requires' => ['philosophy', 'mathematics'], 'bonus' => [], 'unlocks' => 'University'],
        'banking' => ['name' => 'Banking', 'cost' => 180, 'requires' => ['currency', 'education'], 'bonus' => [], 'unlocks' => 'Bank'],
    ];

    public const EMPIRE_NAMES = ['Rome', 'Egypt', 'Babylon', 'Carthage', 'Persia', 'Athens', 'Maurya', 'Han'];

    /** A crest glyph per empire; unknown names get one from the pool. */
    public const CRESTS = ['Rome' => '🦅', 'Egypt' => '𓂀', 'Babylon' => '🦁', 'Carthage' => '🐘', 'Persia' => '🪶', 'Athens' => '🦉', 'Maurya' => '☸', 'Han' => '🐉'];

    public const CREST_POOL = ['⚜', '🐺', '🐂', '🦌', '🐎', '🌞', '🌙', '⚡'];

    public static function crest(string $name): string
    {
        if (isset(self::CRESTS[$name])) {
            return self::CRESTS[$name];
        }

        return self::CREST_POOL[crc32($name) % count(self::CREST_POOL)];
    }

    /** Event categories for the notification toasts, by keyword. */
    public static function eventKind(string $text): string
    {
        return match (true) {
            str_contains($text, 'declared war') || str_contains($text, 'captured') || str_contains($text, 'repelled') => 'war',
            str_contains($text, 'wiped') || str_contains($text, 'fallen') || str_contains($text, 'Famine') || str_contains($text, 'Plague') || str_contains($text, 'Unrest') || str_contains($text, 'treasury') => 'bad',
            str_contains($text, 'discovered') || str_contains($text, 'completed') || str_contains($text, 'Wonder') => 'great',
            str_contains($text, 'Settlers') || str_contains($text, 'Barbarian') || str_contains($text, 'pillag') => 'attention',
            default => 'info',
        };
    }

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

    public const COLORS = ['#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#22c55e', '#f97316', '#a3e635'];

    /** Border radius (squared Euclidean) unlocked at each culture level. */
    public const BORDER_LEVELS = [0 => 2, 15 => 5, 50 => 10];

    /**
     * Yield of a single tile for a given empire: terrain + resource + tech
     * bonuses. Returns food, prod, gold, science.
     */
    public static function tileYield(Tile $tile, ?Empire $empire = null, ?\App\Models\City $city = null): array
    {
        $t = self::TERRAIN[$tile->terrain];
        $y = ['food' => $t['food'], 'prod' => $t['prod'], 'gold' => $t['gold'], 'science' => 0];

        if ($tile->river && $t['land']) {
            foreach (self::RIVER_BONUS as $k => $v) {
                $y[$k] += $v;
            }
            if ($tile->terrain === 'desert') {
                $y['food'] += self::FLOOD_PLAIN_FOOD;
            }
        }

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
        if ($city) {
            foreach ($city->buildings ?? [] as $b) {
                foreach (self::BUILDINGS[$b]['terrain_bonus'][$tile->terrain] ?? [] as $k => $v) {
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
    public static function cityDefense(\App\Models\City $city, int $garrison, int $extra = 0): int
    {
        $d = 5 + $city->population + $garrison + $extra;
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
