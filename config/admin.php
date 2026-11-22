<?php

$emails = preg_split('/\s*,\s*/', (string) env('ADMIN_USER_EMAILS', env('ADMIN_NOTIFICATION_EMAIL', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

return [
    'emails' => array_values(array_unique(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        $emails,
    )))),
];
