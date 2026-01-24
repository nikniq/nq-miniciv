<?php

return [
    'servers_enabled' => env('ADMIN_SERVERS_ENABLED', false),
    'external_logs_enabled' => filter_var(env('APP_EXTLOGS_ENABLED', env('ADMIN_EXTERNAL_LOGS_ENABLED', false)), FILTER_VALIDATE_BOOLEAN),
];
