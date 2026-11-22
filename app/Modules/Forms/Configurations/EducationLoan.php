<?php

/*
 * Disabled example. Discovery loads this file, validates it, and does not
 * register it while enabled is false. Set enabled to true and run
 * `php artisan forms:cache` in production. No other file is required.
 * See docs/universal-form-registry.md.
 */

return [
    'slug' => 'education-loan',
    'form_name' => 'Education Loan',
    'description' => 'Example education loan application. Disabled until enabled is set to true.',
    'enabled' => false,
    'reference_prefix' => 'EDU',
    'merchant_name' => 'Education Loan',
    'fields' => [
        [
            'name' => 'institution',
            'label' => 'Institution',
            'type' => 'text',
            'placeholder' => 'College or university',
            'required' => true,
            'default' => null,
            'validation' => ['required', 'string', 'max:255'],
            'help_text' => null,
            'section' => 'Study',
            'order' => 10,
        ],
        [
            'name' => 'course',
            'label' => 'Course',
            'type' => 'text',
            'required' => true,
            'validation' => ['required', 'string', 'max:255'],
            'section' => 'Study',
            'order' => 20,
        ],
        [
            'name' => 'admission_year',
            'label' => 'Admission year',
            'type' => 'number',
            'required' => true,
            'validation' => ['required', 'integer', 'min:2000', 'max:2100'],
            'section' => 'Study',
            'order' => 30,
        ],
        [
            'name' => 'guardian_email',
            'label' => 'Guardian email',
            'type' => 'email',
            'required' => false,
            'validation' => ['nullable', 'email', 'max:255'],
            'section' => 'Guardian',
            'order' => 40,
        ],
    ],
    'validation_rules' => [
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:20'],
        'address' => ['required', 'string'],
        'institution' => ['required', 'string', 'max:255'],
        'course' => ['required', 'string', 'max:255'],
        'admission_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        'guardian_email' => ['nullable', 'email', 'max:255'],
    ],
    'payment_amount' => '150.00',
    'payment_amount_source' => [
        'type' => 'fixed',
    ],
    'accepted_payment_methods' => ['nagad', 'bkash'],
    'merchant_notification_emails' => [],
    'admin_notification_email' => null,
    'settings' => [
        'payment_enabled' => true,
        'common_fields' => [
            'name' => ['label' => 'Student name'],
            'phone' => ['label' => 'Mobile'],
            'address' => ['label' => 'Address', 'type' => 'textarea'],
        ],
    ],
];
