<?php

namespace App\Modules\Payment\Support;

use App\Modules\Payment\Services\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the configured PaymentGateway driver. Adding another gateway
 * later is a new driver class plus a "payment.gateways.*" config entry —
 * this factory and its callers do not change.
 */
class GatewayManager
{
    public function __construct(private Container $container)
    {
    }

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= config('payment.default_gateway');

        $driverClass = config("payment.gateways.{$name}.driver");

        if (! $driverClass) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not configured.");
        }

        return $this->container->make($driverClass);
    }
}
