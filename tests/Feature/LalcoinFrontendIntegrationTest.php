<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\LalcoinApplication;
use Tests\TestCase;

final class LalcoinFrontendIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_lalcoin_page_submits_through_the_universal_api(): void
    {
        $response = $this->postJson('/submit-form', [
            'form_slug' => 'lalcoin',
            'name' => 'Rahim',
            'phone' => '01712345678',
            'address' => 'Dhaka',
            'payment_amount' => '1.00',
            'merchant_name' => 'Hacker',
            'form_data' => [
                'coin_count' => '10',
                'coin_year' => '2025',
            ],
        ], [
            'Origin' => rtrim((string) config('app.url'), '/'),
        ])->assertOk();

        $application = Application::query()->firstOrFail();

        $response->assertJsonPath('application_id', $application->id);
        $this->assertSame('lalcoin', $application->form_slug);
        $this->assertSame('লাল কয়েন', $application->form_name);
        $this->assertSame('Rahim', $application->name);
        $this->assertSame('01712345678', $application->phone);
        $this->assertSame('Dhaka', $application->address);
        $this->assertSame(['coin_count' => '10', 'coin_year' => '2025'], $application->form_data);
        $this->assertSame('99.00', $application->payment_amount);
        $this->assertSame('লাল কয়েন', $application->merchant_name);
        $this->assertStringStartsWith('LAL-', (string) $application->payment_reference);
        $this->assertNotSame('paid', $application->payment_status);
        $this->assertSame(0, LalcoinApplication::query()->count());

        $this->carrySessionCookies($response);

        $payment = $this->postJson('/init-payment', [
            'application_id' => $application->id,
            'method' => 'nagad',
            'amount' => '1.00',
        ], [
            'Origin' => rtrim((string) config('app.url'), '/'),
        ])->assertOk();

        $this->assertSame('99.00', $payment->json('amount'));
        $this->assertStringContainsString('amount=99.00', (string) $payment->json('url'));
        $this->assertNotSame('paid', $payment->json('payment_status'));
    }

    public function test_a_foreign_origin_still_needs_the_public_credential(): void
    {
        $this->postJson('/submit-form', [
            'form_slug' => 'lalcoin',
            'name' => 'Rahim',
            'phone' => '01712345678',
            'address' => 'Dhaka',
            'form_data' => [
                'coin_count' => '10',
                'coin_year' => '2025',
            ],
        ], [
            'Origin' => 'https://evil.example',
        ])->assertForbidden();

        $this->assertSame(0, Application::query()->count());
    }
}
