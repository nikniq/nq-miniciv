<?php

return [
    /*
     |--------------------------------------------------------------------------
     | License feature toggles
     |--------------------------------------------------------------------------
     |
     | Control which license-related features are enabled via environment
     | variables. Defaults are conservative so turning features on requires
     | explicit env values.
     |
     */

    // Master toggle for all license features
    'enabled' => (bool) env('APP_LICENSES_ENABLED', false),

    // Allow public license validation route (/license/{license_code})
    'public_validation' => (bool) env('APP_LICENSES_PUBLIC_VALIDATION', false),

    // Allow users to purchase licenses (POST /dashboard/licenses)
    'purchase_enabled' => (bool) env('APP_LICENSES_PURCHASE_ENABLED', false),

    // Enable admin CRUD for licenses
    'admin_enabled' => (bool) env('APP_LICENSES_ADMIN', false),
];
