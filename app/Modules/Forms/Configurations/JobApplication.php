<?php

/*
 * nearest_courier remains the registration fee. The fee the server charges
 * is only a value from this field's options. A client-supplied payment_amount
 * is ignored.
 */

return [
    'slug' => 'job-application',
    'form_name' => 'Job Application',
    'description' => 'চাকরির আবেদন। রেজিস্ট্রেশন ফি নির্বাচিত nearest_courier অনুযায়ী।',
    'enabled' => true,
    'reference_prefix' => 'JOB',
    'merchant_name' => config('app.name'),
    'fields' => [
        [
            'name' => 'father_or_husband',
            'label' => 'পিতা/স্বামীর নাম',
            'type' => 'text',
            'required' => false,
            'default' => null,
            'validation' => ['nullable', 'string', 'max:255'],
            'section' => 'ব্যক্তিগত তথ্য',
            'order' => 10,
        ],
        [
            'name' => 'mother',
            'label' => 'মাতার নাম',
            'type' => 'text',
            'required' => false,
            'default' => null,
            'validation' => ['nullable', 'string', 'max:255'],
            'section' => 'ব্যক্তিগত তথ্য',
            'order' => 20,
        ],
        [
            'name' => 'education',
            'label' => 'শিক্ষাগত যোগ্যতা',
            'type' => 'select',
            'required' => true,
            'default' => 'primary',
            'options' => [
                ['value' => 'primary', 'label' => 'প্রাথমিক (৫ম শ্রেণী)'],
                ['value' => 'secondary', 'label' => 'মাধ্যমিক (এস.এস.সি)'],
                ['value' => 'higher_secondary', 'label' => 'উচ্চ মাধ্যমিক (এইচ.এস.সি)'],
                ['value' => 'bachelor', 'label' => 'স্নাতক (ব্যাচেলর)'],
                ['value' => 'masters', 'label' => 'স্নাতকোত্তর (মাস্টার্স)'],
            ],
            'validation' => ['required', 'string', 'in:primary,secondary,higher_secondary,bachelor,masters'],
            'section' => 'যোগ্যতা',
            'order' => 30,
        ],
        [
            'name' => 'courier_help',
            'label' => 'কুরিয়ার সহায়তা',
            'type' => 'radio',
            'required' => true,
            'default' => 'yes',
            'options' => [
                ['value' => 'yes', 'label' => 'হ্যাঁ'],
                ['value' => 'no', 'label' => 'না'],
            ],
            'validation' => ['required', 'string', 'in:yes,no'],
            'section' => 'যোগ্যতা',
            'order' => 40,
        ],
        [
            'name' => 'nearest_courier',
            'label' => 'রেজিস্ট্রেশন ফি',
            'type' => 'select',
            'required' => true,
            'default' => '20',
            'options' => [
                ['value' => '20', 'label' => '২০ টাকা'],
                ['value' => '99', 'label' => '৯৯ টাকা'],
            ],
            'validation' => ['required', 'in:20,99'],
            'help_text' => 'এই নির্বাচনই রেজিস্ট্রেশন ফি। অন্য কোনো পরিমাণ গ্রহণ করা হয় না।',
            'section' => 'ফি',
            'order' => 50,
        ],
    ],
    'validation_rules' => [
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:20'],
        'address' => ['required', 'string'],
        'father_or_husband' => ['nullable', 'string', 'max:255'],
        'mother' => ['nullable', 'string', 'max:255'],
        'education' => ['required', 'string', 'in:primary,secondary,higher_secondary,bachelor,masters'],
        'courier_help' => ['required', 'string', 'in:yes,no'],
        'nearest_courier' => ['required', 'in:20,99'],
    ],
    'payment_amount' => null,
    'payment_amount_source' => [
        'type' => 'field',
        'field' => 'nearest_courier',
        'map' => [
            '20' => '20.00',
            '99' => '99.00',
        ],
    ],
    'accepted_payment_methods' => ['nagad', 'bkash', 'rocket', 'upay'],
    'merchant_notification_emails' => [],
    'admin_notification_email' => filled(config('mail.admin_notification_email'))
        ? config('mail.admin_notification_email')
        : null,
    'settings' => [
        'payment_enabled' => true,
        'common_fields' => [
            'name' => ['label' => 'নাম', 'placeholder' => 'আপনার নাম'],
            'phone' => ['label' => 'মোবাইল নাম্বার', 'placeholder' => '01XXXXXXXXX'],
            'address' => ['label' => 'সম্পূর্ণ ঠিকানা', 'type' => 'textarea'],
        ],
    ],
];
