<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    array_merge(
        ['https://sohojcash.github.io'],
        array_map(static fn (string $value): string => $value, preg_split('/\s*,\s*/', (string) env('FORM_EXTERNAL_ALLOWED_ORIGINS', ''), -1, PREG_SPLIT_NO_EMPTY) ?: []),
    ),
)));

return [
    'paths' => [
        'submit-form',
        'init-payment',
        'lalcoin/applications',
        'lalcoin/applications/init-payment',
    ],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => array_values(array_unique($origins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With', 'X-CSRF-TOKEN', 'X-API-CLIENT-CREDENTIAL'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
