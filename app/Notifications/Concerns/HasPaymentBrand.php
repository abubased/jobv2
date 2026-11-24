<?php

namespace App\Notifications\Concerns;

trait HasPaymentBrand
{
    protected function paymentMethodLabel(): string
    {
        return match (strtolower((string) ($this->application->payment_method ?? ''))) {
            'bkash' => 'বিকাশ',
            'nagad' => 'নগদ',
            default => 'পেমেন্ট',
        };
    }

    protected function brandColors(): array
    {
        return match (strtolower((string) ($this->application->payment_method ?? ''))) {
            'bkash' => ['primary' => '#E2136E', 'dark' => '#9c0e4d', 'light' => '#fde7f0'], // bKash pink
            'nagad' => ['primary' => '#F42534', 'dark' => '#b3121d', 'light' => '#fde6e6'], // Nagad red-orange
            default => ['primary' => '#1f2937', 'dark' => '#111827', 'light' => '#f3f4f6'],
        };
    }
}
