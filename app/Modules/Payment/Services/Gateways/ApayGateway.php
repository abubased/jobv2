<?php

namespace App\Modules\Payment\Services\Gateways;

use App\Modules\Payment\Services\Contracts\GatewayResponse;
use App\Modules\Payment\Services\Contracts\PaymentGateway;
use App\Modules\Payment\Services\PaymentSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stand-in for the real APay API. Simulates account/OTP/PIN verification so
 * the whole flow is testable end to end today. Swapping this for the real
 * HTTP client later means adding a new driver class and a config entry —
 * PaymentGateway callers do not change.
 */
class ApayGateway implements PaymentGateway
{
    private const SIMULATED_OTP = '123456';

    public function verifyAccountNumber(PaymentSession $session, string $accountNumber): GatewayResponse
    {
        if (! preg_match('/^\d{11}$/', $accountNumber)) {
            return GatewayResponse::failure('Enter a valid 11 digit account number.');
        }

        $session->put([
            'account_number' => $accountNumber,
            'otp_code' => self::SIMULATED_OTP,
        ]);

        Log::info('[apay-payment:stub] OTP generated', ['otp' => self::SIMULATED_OTP]);

        return GatewayResponse::success('OTP sent.');
    }

    public function resendOtp(PaymentSession $session): GatewayResponse
    {
        if (! $session->get('account_number')) {
            return GatewayResponse::failure('No account number on file for this session.');
        }

        $session->put(['otp_code' => self::SIMULATED_OTP]);

        Log::info('[apay-payment:stub] OTP resent', ['otp' => self::SIMULATED_OTP]);

        return GatewayResponse::success('OTP resent.');
    }

    public function verifyOtp(PaymentSession $session, string $otp): GatewayResponse
    {
        if ($otp !== $session->get('otp_code')) {
            return GatewayResponse::failure('Incorrect verification code.');
        }

        return GatewayResponse::success('OTP verified.');
    }

    public function verifyPin(PaymentSession $session, string $pin): GatewayResponse
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            return GatewayResponse::failure('Enter a valid 4 digit PIN.');
        }

        return GatewayResponse::success('Payment approved.', [
            'transaction_id' => 'APAY-'.strtoupper(Str::random(10)),
        ]);
    }
}
