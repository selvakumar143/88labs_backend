<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Paths
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Comma-separated list from env, e.g.
    | CORS_ALLOWED_ORIGINS=https://admin.example.com,https://staging-admin.example.com
    */
    'allowed_origins' => array_values(array_filter(array_map(
        static fn ($origin) => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
    ))),

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed / Exposed Headers
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Content-Disposition',
        'Content-Type',
    ],

    'max_age' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Keep false for bearer-token Authorization flows.
    */
    'supports_credentials' => false,
];
