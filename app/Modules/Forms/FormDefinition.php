<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use Illuminate\Validation\ValidationException;

class FormDefinition
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function slug(): string
    {
        return (string) $this->config['slug'];
    }

    public function formName(): string
    {
        return (string) $this->config['form_name'];
    }

    public function enabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    public function referencePrefix(): string
    {
        return (string) $this->config['reference_prefix'];
    }

    public function merchantName(): string
    {
        return (string) $this->config['merchant_name'];
    }

    public function paymentEnabled(): bool
    {
        $settings = $this->settings();

        if (array_key_exists('payment_enabled', $settings)) {
            return (bool) $settings['payment_enabled'];
        }

        return $this->acceptedPaymentMethods() !== [];
    }

    /**
     * @return list<string>
     */
    public function acceptedPaymentMethods(): array
    {
        return array_values($this->config['accepted_payment_methods']);
    }

    public function acceptsMethod(string $method): bool
    {
        return in_array($method, $this->acceptedPaymentMethods(), true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        return array_values($this->config['fields']);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->config['settings'];
    }

    /**
     * @return list<string>
     */
    public function composeAddressFrom(): array
    {
        $fields = $this->settings()['compose_address_from'] ?? [];

        return is_array($fields) ? array_values($fields) : [];
    }

    public function composesAddress(): bool
    {
        return $this->composeAddressFrom() !== [];
    }

    /**
     * @return list<string>
     */
    public function notificationEmails(): array
    {
        $emails = $this->config['merchant_notification_emails'];

        if (is_string($this->config['admin_notification_email']) && $this->config['admin_notification_email'] !== '') {
            $emails[] = $this->config['admin_notification_email'];
        }

        return array_values(array_unique(array_filter($emails, 'is_string')));
    }

    /**
     * Rules for the universal submission request.
     * Payment amount, merchant, reference, and status are not accepted from the client.
     *
     * @return array<string, mixed>
     */
    public function submissionRules(): array
    {
        $rules = [
            'slug' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'address' => $this->composesAddress() ? ['nullable', 'string'] : ['required', 'string'],
            'form_data' => ['nullable', 'array'],
        ];

        foreach (['name', 'phone', 'address'] as $common) {
            if (isset($this->config['validation_rules'][$common])) {
                $rules[$common] = $this->config['validation_rules'][$common];
            }
        }

        foreach ($this->fields() as $field) {
            $name = (string) $field['name'];
            $rules['form_data.'.$name] = $this->config['validation_rules'][$name]
                ?? ($field['validation'] ?? (($field['required'] ?? false) ? ['required'] : ['nullable']));
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function storedFormData(array $formData): array
    {
        $stored = [];

        foreach ($this->fields() as $field) {
            if (($field['store'] ?? true) === false) {
                continue;
            }

            $name = (string) $field['name'];

            if (($field['locked'] ?? false) === true) {
                $stored[$name] = $field['default'] ?? null;

                continue;
            }

            if (array_key_exists($name, $formData)) {
                $stored[$name] = $formData[$name];
            } elseif (array_key_exists('default', $field)) {
                $stored[$name] = $field['default'];
            }
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function composeAddress(array $formData, ?string $submittedAddress): string
    {
        if (! $this->composesAddress()) {
            return trim((string) $submittedAddress);
        }

        if (is_string($submittedAddress) && trim($submittedAddress) !== '') {
            return trim($submittedAddress);
        }

        $parts = [];

        foreach ($this->composeAddressFrom() as $field) {
            $value = $formData[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function resolvePaymentAmount(array $formData): string
    {
        $source = $this->config['payment_amount_source'];
        $type = is_array($source) ? (string) ($source['type'] ?? 'fixed') : 'fixed';

        if ($type === 'config') {
            $fallback = $this->config['payment_amount'];

            return $this->formatAmount(config((string) $source['key'], $fallback));
        }

        if ($type === 'field') {
            $field = (string) $source['field'];
            $value = $formData[$field] ?? null;
            $allowed = $this->allowedAmounts($source, $field);

            $normalized = $this->formatAmount($value);

            if (! in_array($normalized, $allowed, true)) {
                throw ValidationException::withMessages([
                    'form_data.'.$field => 'The registration fee is not an allowed amount.',
                ]);
            }

            return $normalized;
        }

        return $this->formatAmount($this->config['payment_amount']);
    }

    /**
     * Data sent to the universal renderer. Notification recipients stay on the server.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $settings = $this->settings();

        return [
            'slug' => $this->slug(),
            'form_name' => $this->formName(),
            'description' => (string) $this->config['description'],
            'fields' => $this->publicFields(),
            'common_fields' => $settings['common_fields'] ?? [],
            'compose_address_from' => $this->composeAddressFrom(),
            'payment_enabled' => $this->paymentEnabled(),
            'payment_amount' => $this->publicPaymentAmount(),
            'accepted_payment_methods' => $this->acceptedPaymentMethods(),
            'merchant_name' => $this->merchantName(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicFields(): array
    {
        return array_map(function (array $field): array {
            return [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'placeholder' => $field['placeholder'] ?? null,
                'required' => (bool) ($field['required'] ?? false),
                'default' => $field['default'] ?? null,
                'options' => $field['options'] ?? [],
                'help_text' => $field['help_text'] ?? null,
                'section' => $field['section'] ?? null,
                'order' => (int) ($field['order'] ?? 0),
            ];
        }, $this->fields());
    }

    private function publicPaymentAmount(): ?string
    {
        $source = $this->config['payment_amount_source'];
        $type = is_array($source) ? (string) ($source['type'] ?? 'fixed') : 'fixed';

        if ($type === 'field' || ! $this->paymentEnabled()) {
            return null;
        }

        if ($type === 'config') {
            return $this->formatAmount(config((string) $source['key'], $this->config['payment_amount']));
        }

        return $this->formatAmount($this->config['payment_amount']);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return list<string>
     */
    private function allowedAmounts(array $source, string $field): array
    {
        $raw = $source['allowed'] ?? null;

        if (! is_array($raw) || $raw === []) {
            $fieldConfig = collect($this->fields())->firstWhere('name', $field);
            $raw = is_array($fieldConfig) && isset($fieldConfig['options']) ? array_column($fieldConfig['options'], 'value') : [];
        }

        return array_values(array_map(fn (mixed $amount): string => $this->formatAmount($amount), $raw));
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
