<?php

return [
    'slug' => 'lalcoin',
    'form_name' => 'লাল কয়েন',
    'description' => 'লাল কয়েন আবেদন। আবেদন ফি ৯৯.০০ টাকা।',
    'enabled' => true,
    'reference_prefix' => 'LAL',
    'merchant_name' => 'লাল কয়েন',
    'frontend' => [
        'type' => 'external',
        'url' => '/lalcoin',
    ],
    'fields' => [
        [
            'name' => 'coin_count',
            'label' => 'কয়েনের সংখ্যা',
            'type' => 'text',
            'placeholder' => 'কতটি কয়েন',
            'required' => true,
            'default' => null,
            'validation' => ['required', 'string', 'max:255'],
            'help_text' => null,
            'section' => 'কয়েন',
            'order' => 10,
        ],
        [
            'name' => 'coin_year',
            'label' => 'কয়েনের সাল',
            'type' => 'text',
            'placeholder' => 'সাল',
            'required' => true,
            'default' => null,
            'validation' => ['required', 'string', 'max:255'],
            'help_text' => null,
            'section' => 'কয়েন',
            'order' => 20,
        ],
    ],
    'validation_rules' => [
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:20'],
        'address' => ['required', 'string'],
        'coin_count' => ['required', 'string', 'max:255'],
        'coin_year' => ['required', 'string', 'max:255'],
    ],
    'payment_amount' => '99.00',
    'payment_amount_source' => [
        'type' => 'fixed',
    ],
    'accepted_payment_methods' => ['nagad', 'bkash', 'rocket', 'upay'],
    'merchant_notification_emails' => [
        'probasiofficecenter@gmail.com',
        'sarkerhasan498@gmail.com',
    ],
    'admin_notification_email' => null,
    'settings' => [
        'payment_enabled' => true,
        'common_fields' => [
            'name' => ['label' => 'নাম', 'placeholder' => 'আপনার নাম'],
            'phone' => ['label' => 'মোবাইল নাম্বার', 'placeholder' => '01XXXXXXXXX'],
            'address' => ['label' => 'সম্পূর্ণ ঠিকানা', 'type' => 'textarea', 'placeholder' => 'ঠিকানা লিখুন'],
        ],
    ],
];
