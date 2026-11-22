<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use App\Modules\Forms\Exceptions\InvalidFormConfigurationException;

class FormConfigurationValidator
{
    /** @var list<string> */
    public const FIELD_TYPES = [
        'text',
        'email',
        'number',
        'tel',
        'date',
        'textarea',
        'select',
        'radio',
        'checkbox',
        'hidden',
    ];

    /** @var list<string> */
    private const REQUIRED_KEYS = [
        'slug',
        'form_name',
        'description',
        'enabled',
        'reference_prefix',
        'merchant_name',
        'fields',
        'validation_rules',
        'payment_amount',
        'payment_amount_source',
        'accepted_payment_methods',
        'merchant_notification_emails',
        'admin_notification_email',
        'settings',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function validate(string $file, array $config): void
    {
        $errors = [];

        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $config)) {
                $errors[] = "Missing [{$key}].";
            }
        }

        if ($errors !== []) {
            throw new InvalidFormConfigurationException($file, $errors);
        }

        if (! is_string($config['slug']) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $config['slug'])) {
            $errors[] = 'Slug must be a lowercase kebab-case string.';
        }

        if (! is_string($config['form_name']) || trim($config['form_name']) === '') {
            $errors[] = 'Form name must be a non-empty string.';
        }

        if (! is_string($config['description'])) {
            $errors[] = 'Description must be a string.';
        }

        if (! is_bool($config['enabled'])) {
            $errors[] = 'Enabled must be true or false.';
        }

        if (! is_string($config['reference_prefix']) || ! preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $config['reference_prefix'])) {
            $errors[] = 'Reference prefix must be 2-10 uppercase letters or digits and start with a letter.';
        }

        if (! is_string($config['merchant_name']) || trim($config['merchant_name']) === '') {
            $errors[] = 'Merchant name must be a non-empty string.';
        }

        if (! is_array($config['fields'])) {
            $errors[] = 'Fields must be a list.';
        }

        if (! is_array($config['validation_rules'])) {
            $errors[] = 'Validation rules must be an array.';
        }

        if (! is_array($config['settings'])) {
            $errors[] = 'Settings must be an array.';
        }

        $this->validatePayment($config, $errors);
        $this->validateNotifications($config, $errors);
        $fieldNames = $this->validateFields(is_array($config['fields']) ? $config['fields'] : [], $errors);
        $this->validatePaymentField($config, $fieldNames, $errors);

        if ($errors !== []) {
            throw new InvalidFormConfigurationException($file, $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $errors
     */
    private function validatePayment(array $config, array &$errors): void
    {
        $amount = $config['payment_amount'];
        $source = $config['payment_amount_source'];

        if ($amount !== null && ! is_numeric($amount)) {
            $errors[] = 'Payment amount must be numeric or null.';
        }

        if (! is_array($source) || ! isset($source['type']) || ! is_string($source['type'])) {
            $errors[] = 'Payment amount source must define a type.';

            return;
        }

        if (! in_array($source['type'], ['fixed', 'config', 'field'], true)) {
            $errors[] = 'Payment amount source type must be fixed, config, or field.';
        }

        if ($source['type'] === 'fixed' && ($amount === null || ! is_numeric($amount))) {
            $errors[] = 'A fixed payment amount source requires a numeric payment_amount.';
        }

        if ($source['type'] === 'config' && (! isset($source['key']) || ! is_string($source['key']) || $source['key'] === '')) {
            $errors[] = 'A config payment amount source requires a key.';
        }

        if ($source['type'] === 'field' && (! isset($source['field']) || ! is_string($source['field']) || $source['field'] === '')) {
            $errors[] = 'A field payment amount source requires a field name.';
        }

        if (isset($source['allowed']) && ! is_array($source['allowed'])) {
            $errors[] = 'Payment amount allow-list must be an array.';
        }

        if (! is_array($config['accepted_payment_methods'])) {
            $errors[] = 'Accepted payment methods must be a list.';

            return;
        }

        foreach ($config['accepted_payment_methods'] as $method) {
            if (! is_string($method) || ! preg_match('/^[a-z][a-z0-9_-]*$/', $method)) {
                $errors[] = 'Each accepted payment method must be a lowercase slug.';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $errors
     */
    private function validateNotifications(array $config, array &$errors): void
    {
        if (! is_array($config['merchant_notification_emails'])) {
            $errors[] = 'Merchant notification emails must be a list.';
        } else {
            foreach ($config['merchant_notification_emails'] as $email) {
                if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = 'Merchant notification emails must be valid email addresses.';
                    break;
                }
            }
        }

        $admin = $config['admin_notification_email'];

        if ($admin !== null && (! is_string($admin) || filter_var($admin, FILTER_VALIDATE_EMAIL) === false)) {
            $errors[] = 'Admin notification email must be null or a valid email address.';
        }
    }

    /**
     * @param  array<mixed>  $fields
     * @param  array<int, string>  $errors
     * @return list<string>
     */
    private function validateFields(array $fields, array &$errors): array
    {
        $names = [];

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                $errors[] = "Field [{$index}] must be an array.";

                continue;
            }

            $name = $field['name'] ?? null;

            if (! is_string($name) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                $errors[] = "Field [{$index}] needs a name using letters, numbers, and underscores.";

                continue;
            }

            if (in_array($name, ['name', 'phone', 'address', 'slug'], true)) {
                $errors[] = "Field [{$name}] is reserved for the common application columns.";
            }

            if (in_array($name, $names, true)) {
                $errors[] = "Field [{$name}] is duplicated.";
            }

            $names[] = $name;

            $type = $field['type'] ?? null;

            if (! is_string($type) || ! in_array($type, self::FIELD_TYPES, true)) {
                $errors[] = "Field [{$name}] has an unsupported type.";
            }

            if (! isset($field['label']) || ! is_string($field['label'])) {
                $errors[] = "Field [{$name}] needs a label.";
            }

            if (isset($field['required']) && ! is_bool($field['required'])) {
                $errors[] = "Field [{$name}] required must be boolean.";
            }

            if (in_array($type, ['select', 'radio'], true)) {
                if (! isset($field['options']) || ! is_array($field['options']) || $field['options'] === []) {
                    $errors[] = "Field [{$name}] needs options.";
                } else {
                    $this->validateOptions($name, $field['options'], $errors);
                }
            }

            if ($type === 'checkbox' && isset($field['options'])) {
                if (! is_array($field['options'])) {
                    $errors[] = "Field [{$name}] options must be a list.";
                } else {
                    $this->validateOptions($name, $field['options'], $errors);
                }
            }

            $this->validateDependency($name, $field['depends_on'] ?? null, $errors);
        }

        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['name'], $field['depends_on']) || ! is_array($field['depends_on'])) {
                continue;
            }

            $parent = $field['depends_on']['field'] ?? null;

            if (is_string($parent) && ! in_array($parent, $names, true)) {
                $errors[] = "Field [{$field['name']}] depends on unknown field [{$parent}].";
            }
        }

        return $names;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateDependency(string $name, mixed $depends, array &$errors): void
    {
        if ($depends === null) {
            return;
        }

        if (! is_array($depends) || ! isset($depends['field']) || ! is_string($depends['field']) || $depends['field'] === '') {
            $errors[] = "Field [{$name}] depends_on needs a field name.";

            return;
        }

        if ($depends['field'] === $name) {
            $errors[] = "Field [{$name}] cannot depend on itself.";
        }

        $values = $depends['values'] ?? (array_key_exists('value', $depends) ? [$depends['value']] : null);

        if (! is_array($values) || $values === []) {
            $errors[] = "Field [{$name}] depends_on needs a value or values list.";

            return;
        }

        foreach ($values as $value) {
            if (! is_int($value) && ! is_string($value)) {
                $errors[] = "Field [{$name}] depends_on values must be strings or integers.";
                break;
            }
        }
    }

    /**
     * @param  array<mixed>  $options
     * @param  array<int, string>  $errors
     */
    private function validateOptions(string $field, array $options, array &$errors): void
    {
        foreach ($options as $option) {
            if (! is_array($option) || ! array_key_exists('value', $option) || ! isset($option['label']) || ! is_string($option['label'])) {
                $errors[] = "Field [{$field}] options must use value and label.";
                break;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $fieldNames
     * @param  array<int, string>  $errors
     */
    private function validatePaymentField(array $config, array $fieldNames, array &$errors): void
    {
        $source = $config['payment_amount_source'];

        if (! is_array($source) || ($source['type'] ?? null) !== 'field') {
            return;
        }

        $field = $source['field'] ?? null;

        if (! is_string($field) || ! in_array($field, $fieldNames, true)) {
            $errors[] = 'Payment amount field must be one of the configured fields.';

            return;
        }

        $allowed = $source['allowed'] ?? null;
        $map = $source['map'] ?? null;
        $hasMap = false;
        $fieldConfig = collect($config['fields'])->firstWhere('name', $field);
        $optionValues = is_array($fieldConfig) ? $this->optionValues($fieldConfig) : [];

        if (isset($source['map']) && ! is_array($map)) {
            $errors[] = 'Payment amount map must be an array.';
        }

        if (is_array($map) && $map !== []) {
            $hasMap = true;

            foreach ($map as $from => $amount) {
                if ((! is_string($from) && ! is_int($from)) || ! is_numeric($amount)) {
                    $errors[] = 'Payment amount map must use string or integer keys and numeric amounts.';
                    break;
                }
            }
        }

        if (! $hasMap && (! is_array($allowed) || $allowed === []) && $optionValues === []) {
            $errors[] = 'A field payment amount needs a map, an allow-list, or field options. The submitted value is never used unless it matches that server-side rule.';
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function optionValues(array $field): array
    {
        if (! isset($field['options']) || ! is_array($field['options'])) {
            return [];
        }

        return array_values(array_map(
            static fn (array $option): string => (string) $option['value'],
            array_filter($field['options'], 'is_array'),
        ));
    }
}
