<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Frontpage Theme
    |--------------------------------------------------------------------------
    |
    | Select which theme provides the frontpage view. The theme should be
    | a folder inside `resources/views/themes/{theme}` containing a
    | `frontpage.blade.php` file. Set via the `FRONTPAGE_THEME` env var.
    |
    */
    'theme' => env('FRONTPAGE_THEME', env('FRONTPAGE_THEME', 'games')),
];
