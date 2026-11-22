<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PaymentInitFlowTest extends TestCase
{
    public function test_it_creates_an_application_and_returns_a_signed_nagad_payment_url(): void
    {
        $applicationResponse = $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->postJson('/job-applications', [
                'name' => 'Test User',
                'father_or_husband' => 'Test Father',
                'mother' => 'Test Mother',
                'phone' => '01700000001',
                'address' => 'Dhaka',
                'education' => 'primary',
                'courier_help' => 'yes',
                'nearest_courier' => '20',
                'submitted' => true,
            ]);

        $applicationResponse->assertOk();

        $applicationId = $applicationResponse->json('application.id');
        $this->assertNotNull($applicationId);

        $paymentResponse = $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->postJson('/job-applications/init-payment', [
                'application_id' => $applicationId,
                'method' => 'nagad',
            ]);

        $paymentResponse->assertOk();
        $paymentResponse->assertJsonPath('method', 'nagad');
        $this->assertStringContainsString('/payment/start', $paymentResponse->json('url'));
        $this->assertStringContainsString('reference_no', $paymentResponse->json('url'));
        $this->assertStringContainsString('amount=20.00', $paymentResponse->json('url'));
    }
}
