<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Lab enabled
    |--------------------------------------------------------------------------
    |
    | Toggle whether the public API Lab is available at `/api-lab`. Set
    | `API_LAB_ENABLED=false` in the environment to hide routes and links.
    |
    */
    'enabled' => filter_var(env('API_LAB_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
