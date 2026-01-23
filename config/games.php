<?php

return [
    // Enable or disable the Games feature.
    'enabled' => env('GAMES_ENABLED', false),

    // Category name in the `products` table used to identify games.
    'category' => env('GAMES_CATEGORY', 'Game'),

    // If no products are found in the DB for the above category, optionally
    // fall back to the curated list below. Default is false to only show DB products.
    'fallback_to_config' => env('GAMES_FALLBACK_TO_CONFIG', false),

    // Curated list of games to show in the Games theme.
    // Each entry: title, slug (optional), description, url (optional), thumbnail (optional).
    'list' => [
        [
            'title' => 'Space Runner',
            'slug' => 'space-runner',
            'description' => 'A fast-paced endless runner set among asteroids and drifting satellites.',
        ],
        [
            'title' => 'Puzzle Grove',
            'slug' => 'puzzle-grove',
            'description' => 'Relaxing puzzle challenges with handcrafted levels and soothing visuals.',
        ],
        [
            'title' => 'Neon Racers',
            'slug' => 'neon-racers',
            'description' => 'Retro-style top-down racing with tight controls and online leaderboards.',
        ],
    ],
];
