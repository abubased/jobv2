<?php

namespace App\Modules\Payment\Services\Contracts;

use App\Modules\Payment\Services\PaymentSession;

/**
 * Strategy contract every payment gateway driver must implement. Step
 * controllers depend only on this interface, never on a concrete gateway,
 * so a new gateway (or a new APay API version) is a new driver class plus a
 * config entry — no controller or view changes.
 */
interface PaymentGateway
{
    public function verifyAccountNumber(PaymentSession $session, string $accountNumber): GatewayResponse;

    public function resendOtp(PaymentSession $session): GatewayResponse;

    public function verifyOtp(PaymentSession $session, string $otp): GatewayResponse;

    public function verifyPin(PaymentSession $session, string $pin): GatewayResponse;
}
