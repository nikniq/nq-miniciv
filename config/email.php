<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Features
    |--------------------------------------------------------------------------
    |
    | Toggle email-related features such as the diagnostic "Send test email"
    | page. Set `APP_EMAIL_ENABLED=false` in the environment to hide links
    | and routes for sending test messages.
    |
    */
    'enabled' => filter_var(env('APP_EMAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
