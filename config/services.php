<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // "Sign in with nikniq" (OAuth 2, Passport-style endpoints by default).
    // Leave NIKNIQ_CLIENT_ID blank to hide the button.
    'nikniq' => [
        'client_id' => env('NIKNIQ_CLIENT_ID'),
        'client_secret' => env('NIKNIQ_CLIENT_SECRET'),
        'redirect' => env('NIKNIQ_REDIRECT_URI', '/auth/nikniq/callback'),
        'url' => env('NIKNIQ_URL', 'https://nikniq.com'),
        'authorize_path' => env('NIKNIQ_AUTHORIZE_PATH', '/oauth/authorize'),
        'token_path' => env('NIKNIQ_TOKEN_PATH', '/oauth/token'),
        'user_path' => env('NIKNIQ_USER_PATH', '/api/user'),
        'scopes' => array_filter(explode(' ', (string) env('NIKNIQ_SCOPES', ''))),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
