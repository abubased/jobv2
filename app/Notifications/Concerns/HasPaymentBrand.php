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
    protected function applicantNumber(): string
    {   
        $number = (string) (
            $this->application->meta_info['number'] ?? ''
        );

        // Convert Bangla and other Unicode digits to English digits
        $number = preg_replace_callback('/\p{N}/u', function ($matches) {
            $digit = $matches[0];

            if (preg_match('/^[0-9]$/', $digit)) {
                return $digit;
            }

            $value = mb_convert_kana($digit, 'n', 'UTF-8');

            return preg_match('/^[0-9]$/', $value)
                ? $value
                : $digit;
        }, $number);

        // Remove spaces, dashes and other non-digit characters
        $number = preg_replace('/\D/', '', $number);

        // Normalize Bangladesh country code
        if (str_starts_with($number, '880')) {
            $number = '0' . substr($number, 3);
        } elseif (str_starts_with($number, '1') && strlen($number) === 10) {
            $number = '0' . $number;
        }

        // Validate Bangladesh mobile number
        if (!preg_match('/^01[3-9]\d{8}$/', $number)) {
            return "Wrong Number - {$number}";
        }

        return $number;
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
