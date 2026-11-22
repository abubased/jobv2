<?php

namespace App\Modules\Payment\Services\Contracts;

/**
 * Immutable result of a single gateway call. Every PaymentGateway method
 * returns one of these so controllers never branch on gateway-specific
 * exceptions or response shapes.
 */
final class GatewayResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $message,
        public readonly array $data,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(?string $message = null, array $data = []): self
    {
        return new self(true, $message, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function failure(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }
}
