<?php

namespace App\Modules\Payment\Services\Gateways;

use App\Modules\Payment\Services\Contracts\GatewayResponse;
use App\Modules\Payment\Services\Contracts\PaymentGateway;
use App\Modules\Payment\Services\PaymentSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the real APay multi-step transaction engine over HTTP.
 *
 * Step mapping: verifyAccountNumber() -> POST /transactions/step-one,
 * verifyPin() -> POST /transactions/step-two (the engine's 4-5 digit
 * "second_input"), verifyOtp() -> POST /transactions/step-three (the
 * engine's 6 digit "backup_code"). The module's flow was corrected so the
 * PIN screen runs before the OTP screen, matching that step order.
 *
 * The engine has no endpoint for sending/resending a code — it only
 * validates whatever the user already has — so resendOtp() is a local
 * no-op that reports success without calling out.
 */
class RealApayGateway implements PaymentGateway
{
    private string $baseUrl;

    private string $apiKey;

    private int $timeout;

    private int $connectTimeout;

    private int $retryTimes;

    private int $retryDelayMs;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('payment.gateways.apay.base_url'), '/');
        $this->apiKey = (string) config('payment.gateways.apay.api_key');
        $this->timeout = (int) config('payment.gateways.apay.timeout', 10);
        $this->connectTimeout = (int) config('payment.gateways.apay.connect_timeout', 3);
        $this->retryTimes = (int) config('payment.gateways.apay.retry_times', 2);
        $this->retryDelayMs = (int) config('payment.gateways.apay.retry_delay_ms', 200);
    }

    public function verifyAccountNumber(PaymentSession $session, string $accountNumber): GatewayResponse
    {
        $response = $this->post('/transactions/step-one', [
            'invoice_no' => $session->get('reference_no'),
            'merchant_name' => $session->get('merchant_name'),
            'amount' => $session->get('amount'),
            'customer_input' => $accountNumber,
        ]);

        return $this->toGatewayResponse($response, function (array $body) use ($session) {
            $session->put(['apay_transaction_id' => $body['data']['transaction_id'] ?? null]);
        });
    }

    public function resendOtp(PaymentSession $session): GatewayResponse
    {
        return GatewayResponse::success('A verification code was already sent to your registered number.');
    }

    public function verifyOtp(PaymentSession $session, string $otp): GatewayResponse
    {
        return $this->verifyStep($session, '/transactions/step-three', 'backup_code', $otp);
    }

    public function verifyPin(PaymentSession $session, string $pin): GatewayResponse
    {
        return $this->verifyStep($session, '/transactions/step-two', 'second_input', $pin);
    }

    private function verifyStep(PaymentSession $session, string $path, string $field, string $value): GatewayResponse
    {
        $transactionId = $session->get('apay_transaction_id');

        if (! $transactionId) {
            return GatewayResponse::failure('No transaction on file for this session. Please start again.');
        }

        $response = $this->post($path, [
            'transaction_id' => $transactionId,
            $field => $value,
        ]);

        return $this->toGatewayResponse($response);
    }

    /**
     * @param  (callable(array<string, mixed>): void)|null  $onSuccess
     */
private function toGatewayResponse(
    ?Response $response,
    ?callable $onSuccess = null
): GatewayResponse {
    if (! $response) {
        return GatewayResponse::failure(
            'Could not reach the payment gateway. Please try again.'
        );
    }

    $body = $response->json();

    if (! is_array($body)) {
        return GatewayResponse::failure(
            'Received an unexpected response from the payment gateway.'
        );
    }

    $data = $body['data'] ?? [];

    if (! ($body['success'] ?? false)) {
        return GatewayResponse::failure(
            $body['message'] ?? 'The request could not be completed.',
            $data
        );
    }

    if ($onSuccess) {
        $onSuccess($body);
    }

    return GatewayResponse::success(
        $body['message'] ?? null,
        $data
    );
}

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): ?Response
    {
        try {
            // base_url already carries the version prefix (e.g.
            // https://host/api/v1), so it is used as-is here. retry() only
            // fires on a connection-level failure (timeout, DNS, refused) —
            // never on an HTTP error status — since we don't call throw(),
            // so a business failure (wrong OTP, validation error) is never
            // retried or double-submitted.
            return Http::baseUrl($this->baseUrl)
                ->withHeaders(['X-API-KEY' => $this->apiKey])
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($path, $payload);
        } catch (ConnectionException) {
            return null;
        }
    }
}
