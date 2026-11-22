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
    'apay' => [
    'base_url' => env('APAY_BASE_URL', 'http://127.0.0.1:8000/api/v1'),
    'key' => env('APAY_API_KEY'),
    'timeout' => (int) env('APAY_TIMEOUT', 10),
    'connect_timeout' => (int) env('APAY_CONNECT_TIMEOUT', 3),
    'retry_times' => (int) env('APAY_RETRY_TIMES', 2),
    'retry_delay_ms' => (int) env('APAY_RETRY_DELAY_MS', 200),
    ],
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
