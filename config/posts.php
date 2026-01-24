<?php

return [
    // Enable or disable the posts feature. Controlled via env `POSTS_ENABLED`.
    // Allow either `POST_ENABLED` (legacy/typo) or `POSTS_ENABLED` to control feature.
    'enabled' => env('POST_ENABLED', env('POSTS_ENABLED', false)),

    // Model used for posts. Set via env `POSTS_MODEL` to override.
    // Example in .env: POSTS_MODEL=App\\Models\\WpPost
    'model' => env('POSTS_MODEL', App\Models\WpPost::class),
];
