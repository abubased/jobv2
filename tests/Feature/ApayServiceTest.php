<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Apay\ApayException;
use App\Services\Apay\ApayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Http::fake() exercises the concrete ApayService directly — no interface or
 * mock needed, since there's only one implementation. For tests of
 * controllers/services that consume ApayService, prefer Http::fake() too
 * (over mocking ApayService itself): it's a concrete class with no
 * interface, and faking the transport keeps the test honest about what
 * ApayService actually does with a response.
 */
final class ApayServiceTest extends TestCase
{
    #[Test]
    public function step_one_sends_the_api_key_header_and_payload(): void
    {
        Http::fake([
            '*/transactions/step-one' => Http::response([
                'success' => true,
                'code' => 'STEP_1_COMPLETED',
                'message' => 'Transaction created.',
                'data' => ['transaction_id' => 'uuid-123'],
                'errors' => null,
            ], 201),
        ]);

        $response = app(ApayService::class)->stepOne('INV-1', 'Nimbus Retail', '1000.00', 'ref-abc');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-API-KEY', (string) config('services.apay.key'))
            && $request['invoice_no'] === 'INV-1'
            && $request['amount'] === '1000.00');

        $this->assertTrue($response->success);
        $this->assertSame('uuid-123', $response->transactionId());
    }

    #[Test]
    public function a_failed_verification_returns_a_response_instead_of_throwing(): void
    {
        Http::fake([
            '*/transactions/step-three' => Http::response([
                'success' => false,
                'code' => 'VERIFICATION_FAILED',
                'message' => 'Incorrect code.',
                'data' => ['retry' => [
                    'retry_allowed' => true, 'retry_after' => 30,
                    'attempts_used' => 1, 'attempts_remaining' => 2, 'max_attempts' => 3,
                ]],
                'errors' => null,
            ], 200),
        ]);

        $response = app(ApayService::class)->stepThree('uuid-123', '000000');

        $this->assertFalse($response->success);
        $this->assertSame('VERIFICATION_FAILED', $response->code);
        $this->assertSame(2, $response->retry()['attempts_remaining']);
    }

    #[Test]
    public function a_cancellation_returns_success_false_without_throwing(): void
    {
        Http::fake([
            '*/transactions/cancel' => Http::response([
                'success' => false,
                'code' => 'USER_CANCELLED',
                'message' => 'Transaction cancelled.',
                'data' => [],
                'errors' => null,
            ], 200),
        ]);

        $response = app(ApayService::class)->cancel('uuid-123');

        $this->assertFalse($response->success);
        $this->assertSame('USER_CANCELLED', $response->code);
    }

    #[Test]
    public function a_validation_failure_throws_with_field_errors(): void
    {
        Http::fake([
            '*/transactions/step-one' => Http::response([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed',
                'data' => [],
                'errors' => ['amount' => ['The amount field is required.']],
            ], 422),
        ]);

        try {
            app(ApayService::class)->stepOne('', '', '', '');
            $this->fail('Expected an ApayException.');
        } catch (ApayException $e) {
            $this->assertTrue($e->isValidation());
            $this->assertSame(['amount' => ['The amount field is required.']], $e->errors);
        }
    }

    #[Test]
    public function an_illegal_step_transition_throws(): void
    {
        Http::fake([
            '*/transactions/step-two' => Http::response([
                'success' => false,
                'code' => 'INVALID_STEP',
                'message' => 'Step 2 is not allowed for this transaction.',
                'data' => [],
                'errors' => null,
            ], 409),
        ]);

        $this->expectException(ApayException::class);

        app(ApayService::class)->stepTwo('uuid-123', '04521');
    }

    #[Test]
    public function a_connection_failure_is_translated_to_apay_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(ApayException::class);

        app(ApayService::class)->stepOne('INV-1', 'Nimbus Retail', '10.00', 'ref');
    }
}
