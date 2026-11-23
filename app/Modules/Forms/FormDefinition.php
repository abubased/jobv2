<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use Illuminate\Validation\Rule;
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
    public function merchantNotificationEmails(): array
    {
        $emails = $this->config['merchant_notification_emails'] ?? [];

        return is_array($emails) ? array_values(array_filter($emails, 'is_string')) : [];
    }

    /**
     * @return list<string>
     */
    public function notificationEmails(): array
    {
        $emails = $this->merchantNotificationEmails();

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
            $rules['form_data.'.$name] = $this->rulesForField($field);
        }

        return $rules;
    }

    /**
     * Configured rules, with depends_on applied as server-side conditional rules.
     * A dependent value is excluded unless its parent matches, and a required
     * dependent field is required only while that condition is true.
     *
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    private function rulesForField(array $field): array
    {
        $name = (string) $field['name'];
        $configured = $this->config['validation_rules'][$name] ?? ($field['validation'] ?? null);

        if ($configured === null) {
            $rules = ($field['required'] ?? false) ? ['required'] : ['nullable'];
        } elseif (is_string($configured)) {
            $rules = explode('|', $configured);
        } else {
            $rules = array_values($configured);
        }

        $dependency = $this->dependency($field);

        if ($dependency === null) {
            return $rules;
        }

        [$parent, $values] = $dependency;

        $rules = array_values(array_filter(
            $rules,
            static fn (mixed $rule): bool => ! in_array($rule, ['required', 'nullable'], true)
                && ! (is_string($rule) && (str_starts_with($rule, 'required_if:') || str_starts_with($rule, 'exclude_unless:'))),
        ));

        array_unshift(
            $rules,
            Rule::excludeIf(function () use ($parent, $values): bool {
                return ! in_array(request()->input('form_data.'.$parent), $values, false);
            }),
            'nullable',
        );

        if (($field['required'] ?? false) === true) {
            $rules[] = Rule::requiredIf(function () use ($parent, $values): bool {
                return in_array(request()->input('form_data.'.$parent), $values, false);
            });
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{0: string, 1: list<int|string>}|null
     */
    private function dependency(array $field): ?array
    {
        $depends = $field['depends_on'] ?? null;

        if (! is_array($depends) || ! isset($depends['field']) || ! is_string($depends['field'])) {
            return null;
        }

        $values = $depends['values'] ?? (array_key_exists('value', $depends) ? [$depends['value']] : []);

        if (! is_array($values) || $values === []) {
            return null;
        }

        $normalized = [];

        foreach ($values as $value) {
            if (is_int($value) || is_string($value)) {
                $normalized[] = $value;
            }
        }

        if ($normalized === []) {
            return null;
        }

        return [$depends['field'], $normalized];
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
     * Records who supplied each stored field at submission time.
     * Locked values and defaults used because the applicant omitted the field are backend values.
     *
     * @param  array<string, mixed>  $formData
     * @return array<string, string>
     */
    public function fieldSources(array $formData): array
    {
        $sources = [];

        foreach ($this->fields() as $field) {
            if (($field['store'] ?? true) === false) {
                continue;
            }

            $name = (string) $field['name'];

            if (($field['locked'] ?? false) === true) {
                $sources[$name] = 'backend';

                continue;
            }

            if (array_key_exists($name, $formData)) {
                $sources[$name] = 'applicant';
            } elseif (array_key_exists('default', $field)) {
                $sources[$name] = 'backend';
            }
        }

        return $sources;
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
            $mapped = $this->mappedAmount($source, $value);

            if ($mapped !== null) {
                return $mapped;
            }

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
                'depends_on' => $this->publicDependency($field),
            ];
        }, $this->fields());
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{field: string, values: list<int|string>}|null
     */
    private function publicDependency(array $field): ?array
    {
        $dependency = $this->dependency($field);

        if ($dependency === null) {
            return null;
        }

        return [
            'field' => $dependency[0],
            'values' => $dependency[1],
        ];
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
     * Explicit value-to-amount map. The submitted value is a key, not the fee,
     * unless that key is present and its mapped amount is numeric.
     *
     * @param  array<string, mixed>  $source
     */
    private function mappedAmount(array $source, mixed $value): ?string
    {
        $map = $source['map'] ?? null;

        if (! is_array($map) || $map === [] || ! is_scalar($value)) {
            return null;
        }

        $mapped = $map[(string) $value] ?? null;

        if (! is_numeric($mapped)) {
            throw ValidationException::withMessages([
                'form_data.'.(string) $source['field'] => 'The registration fee is not an allowed amount.',
            ]);
        }

        return $this->formatAmount($mapped);
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
