<?php

return [
    'servers_enabled' => filter_var(env('APP_SERVERS_ENABLED', env('ADMIN_SERVERS_ENABLED', false)), FILTER_VALIDATE_BOOLEAN),
    'external_logs_enabled' => filter_var(env('APP_EXTLOGS_ENABLED', env('ADMIN_EXTERNAL_LOGS_ENABLED', false)), FILTER_VALIDATE_BOOLEAN),
];
