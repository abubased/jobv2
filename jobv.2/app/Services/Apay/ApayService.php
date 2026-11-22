<?php

declare(strict_types=1);

namespace App\Services\Apay;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Client for the apay multi-step transaction engine.
 *
 * Deliberately a single concrete class: no interface, no repository, no
 * dedicated service provider binding. Laravel's container autowires this
 * directly into any constructor that type-hints it, since the constructor
 * takes no arguments the container can't resolve on its own. If a second
 * consuming app or a second provider with the same shape ever shows up,
 * extract an ApayClientInterface at that point — not before.
 */
final class ApayService
{
    private readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly int $timeout;

    private readonly int $connectTimeout;

    private readonly int $retryTimes;

    private readonly int $retryDelayMs;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.apay.base_url'), '/');
        $this->apiKey = (string) config('services.apay.key');
        $this->timeout = (int) config('services.apay.timeout', 10);
        $this->connectTimeout = (int) config('services.apay.connect_timeout', 3);
        $this->retryTimes = (int) config('services.apay.retry_times', 2);
        $this->retryDelayMs = (int) config('services.apay.retry_delay_ms', 200);
    }

    /**
     * STEP 1 — create a transaction.
     *
     * @param  string  $amount  Decimal string (e.g. "1500.00") — pass as a
     *                          string to avoid float rounding surprises.
     * @param  array<string, mixed>  $metadata
     */
    public function stepOne(
        string $invoiceNo,
        string $merchantName,
        string $amount,
        string $customerInput,
        array $metadata = [],
    ): ApayResponse {
        $payload = [
            'invoice_no' => $invoiceNo,
            'merchant_name' => $merchantName,
            'amount' => $amount,
            'customer_input' => $customerInput,
        ];

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        return $this->post('transactions/step-one', $payload);
    }

    /**
     * STEP 2 — record the second input.
     */
    public function stepTwo(string $transactionId, string $secondInput): ApayResponse
    {
        return $this->post('transactions/step-two', [
            'transaction_id' => $transactionId,
            'second_input' => $secondInput,
        ]);
    }

    /**
     * STEP 3 — verify the backup code.
     *
     * A wrong code is not an exception: it comes back with success:false and
     * a `retry` block in `data`. Only an exhausted-retry resubmission or a
     * genuine fault throws.
     */
    public function stepThree(string $transactionId, string $backupCode): ApayResponse
    {
        return $this->post('transactions/step-three', [
            'transaction_id' => $transactionId,
            'backup_code' => $backupCode,
        ]);
    }

    /**
     * Cancel a transaction. Success is reported as success:false by apay's
     * own contract — the flow didn't complete, even though the cancellation
     * itself worked.
     */
    public function cancel(string $transactionId): ApayResponse
    {
        return $this->post('transactions/cancel', ['transaction_id' => $transactionId]);
    }

    /**
     * Read-only transaction status. Values apay returns here are already masked.
     */
    public function status(string $transactionId): ApayResponse
    {
        return $this->handle(
            fn (): Response => $this->client()->get("transactions/{$transactionId}"),
            'status',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $endpoint, array $payload): ApayResponse
    {
        return $this->handle(
            fn (): Response => $this->client()->post($endpoint, $payload),
            $endpoint,
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-API-KEY' => $this->apiKey])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->retry(
                $this->retryTimes,
                $this->retryDelayMs,
                // Retry connection failures only — never retry once any
                // response was received, even a business failure or a 4xx.
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                // Laravel's retry() throws a RequestException on any failed
                // response once retries are exhausted unless told not to —
                // that would bypass handle()'s own exception mapping below,
                // so failed responses must come back as plain Response objects.
                throw: false,
            );
    }

    /**
     * Send the request, log the outcome, and translate a non-2xx response
     * into an ApayException. A 2xx response is always returned as-is, even
     * when its body carries success:false — that is a business outcome, not
     * a fault.
     *
     * @param  Closure(): Response  $send
     */
    private function handle(Closure $send, string $endpoint): ApayResponse
    {
        $correlationId = (string) Str::uuid();
        $startedAt = microtime(true);

        try {
            $response = $send();
        } catch (ConnectionException) {
            Log::channel('apay')->error('apay.connection_failed', [
                'correlation_id' => $correlationId,
                'endpoint' => $endpoint,
            ]);

            throw new ApayException(
                'Could not reach the apay service.',
                apayCode: 'CONNECTION_FAILED',
                httpStatus: 0,
            );
        }

        $body = ApayResponse::fromArray($response->json() ?? []);

        Log::channel('apay')->info('apay.request', [
            'correlation_id' => $correlationId,
            'endpoint' => $endpoint,
            'transaction_id' => $body->transactionId(),
            'code' => $body->code,
            'status' => $response->status(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        if ($response->successful()) {
            return $body;
        }

        throw new ApayException(
            $body->message !== '' ? $body->message : 'apay request failed.',
            apayCode: $body->code,
            httpStatus: $response->status(),
            errors: $body->errors,
        );
    }
}
