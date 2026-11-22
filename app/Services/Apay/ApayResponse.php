<?php

declare(strict_types=1);

namespace App\Services\Apay;

/**
 * A decoded apay API response.
 *
 * `success` can be false on a 2xx HTTP status for legitimate business
 * outcomes — a verification failure with retries left, or a cancellation.
 * Check this field (or `code`), not the HTTP status, to decide how to render
 * the outcome. Only genuine faults reach the caller as an ApayException.
 */
final readonly class ApayResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $errors
     */
    public function __construct(
        public bool $success,
        public string $code,
        public string $message,
        public array $data,
        public ?array $errors,
    ) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromArray(array $json): self
    {
        return new self(
            success: (bool) ($json['success'] ?? false),
            code: (string) ($json['code'] ?? 'UNKNOWN'),
            message: (string) ($json['message'] ?? ''),
            data: $json['data'] ?? [],
            errors: $json['errors'] ?? null,
        );
    }

    public function transactionId(): ?string
    {
        return $this->data['transaction_id'] ?? null;
    }

    /**
     * Retry state from a STEP 3 outcome, when present.
     *
     * @return array{retry_allowed: bool, retry_after: int|null, attempts_used: int, attempts_remaining: int, max_attempts: int}|null
     */
    public function retry(): ?array
    {
        return $this->data['retry'] ?? null;
    }
}
