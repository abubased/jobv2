<?php

declare(strict_types=1);

namespace App\Services\Apay;

use RuntimeException;

/**
 * Thrown for genuine faults calling apay: a missing/invalid API key,
 * validation failure, an illegal step transition, rate limiting, a server
 * error, or a connection failure.
 *
 * Business outcomes carried as success:false on a 2xx status (a wrong
 * verification code with retries left, a cancellation) are NOT exceptions —
 * see ApayService::handle().
 */
final class ApayException extends RuntimeException
{
    /**
     * @param  string  $apayCode  apay's response `code`, or a synthetic one
     *                            (e.g. CONNECTION_FAILED) when apay never responded.
     *                            Named to avoid colliding with the built-in,
     *                            non-readonly Exception::$code property.
     * @param  int  $httpStatus  0 when the request never reached apay at all.
     * @param  array<string, mixed>|null  $errors  Field errors, present only for VALIDATION_FAILED.
     */
    public function __construct(
        string $message,
        public readonly string $apayCode,
        public readonly int $httpStatus,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    public function isValidation(): bool
    {
        return $this->apayCode === 'VALIDATION_FAILED';
    }
}
