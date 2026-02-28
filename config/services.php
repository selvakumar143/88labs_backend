<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'forex' => [
        'base_url' => env('FOREX_API_BASE_URL', 'https://api.forexrateapi.com/v1/latest'),
        'api_key' => env('FOREX_API_KEY'),
        'base' => env('FOREX_BASE', 'USDT'),
        'currencies' => env('FOREX_CURRENCIES', 'USD,EUR'),
        'cache_key' => env('FOREX_CACHE_KEY', 'forex.latest'),
        'cache_ttl_seconds' => (int) env('FOREX_CACHE_TTL_SECONDS', 3900),
        'http_timeout' => (int) env('FOREX_HTTP_TIMEOUT', 15),
    ],

];
