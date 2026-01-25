<?php

return [
    // Enable or disable the posts feature. Preferred env: `APP_POSTS_ENABLED`.
    // Legacy fallbacks: `APP_POST_ENABLED`, `POST_ENABLED` and `POSTS_ENABLED` are still supported.
    'enabled' => filter_var(env('APP_POSTS_ENABLED', env('APP_POST_ENABLED', env('POST_ENABLED', env('POSTS_ENABLED', false)))), FILTER_VALIDATE_BOOLEAN),

    // Model used for posts. Set via env `POSTS_MODEL` to override.
    // Example in .env: POSTS_MODEL=App\\Models\\WpPost
    'model' => env('POSTS_MODEL', App\Models\WpPost::class),
];
