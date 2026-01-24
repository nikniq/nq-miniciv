<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Logs Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Controls whether the Logs links/routes are exposed in the UI. Set
    | `APP_LOGS_ENABLED=false` to hide log links (dashboard, admin) when
    | you don't want logs accessible from the UI.
    |
    */
    'enabled' => filter_var(env('APP_LOGS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
