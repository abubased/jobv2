<?php

return [

    // URL prefix the module's routes are mounted under, e.g. /payment/...
    'route_prefix' => env('PAYMENT_ROUTE_PREFIX', 'payment'),

    // Prefix used for all named routes, e.g. apay-payment.start
    'route_name_prefix' => 'apay-payment',

    // Top-level session key the module stores its state under.
    'session_prefix' => 'apay_payment',

    // Which entry in "gateways" below is used when none is specified.
    'default_gateway' => env('PAYMENT_GATEWAY', 'apay'),

    // Selects a CSS/view variant; only "default" ships today.
    'theme' => env('PAYMENT_THEME', 'default'),

    // Ordered list of steps the payment flow walks through.
    'flow' => [
        'steps' => ['account-number', 'pin', 'otp'],
    ],

    'gateways' => [
        'apay' => [
            'driver' => \App\Modules\Payment\Services\Gateways\RealApayGateway::class,

            // Real APay transaction engine — see README for the request/response shape.
            // base_url is used as-is (no path is appended), so it must already
            // include the API version prefix, e.g. https://host/api/v1.
            'base_url' => env('APAY_BASE_URL'),
            'api_key' => env('APAY_API_KEY'),
            'timeout' => (int) env('APAY_TIMEOUT', 10),
            'connect_timeout' => (int) env('APAY_CONNECT_TIMEOUT', 3),
            'retry_times' => (int) env('APAY_RETRY_TIMES', 2),
            'retry_delay_ms' => (int) env('APAY_RETRY_DELAY_MS', 200),
        ],
    ],

];
