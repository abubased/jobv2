<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Modules\Payment\SignedPaymentUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class FormPaymentInitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_each_form_initializes_only_its_configured_fee_and_methods(): void
    {
        $lalcoin = $this->submit('lalcoin', [
            'coin_count' => '10',
            'coin_year' => '2026',
        ]);

        foreach (['nagad', 'bkash', 'rocket', 'upay'] as $method) {
            $payment = $this->initialize($lalcoin, $method, [
                'amount' => '1.00',
                'merchant_name' => 'Hacker',
                'payment_status' => 'paid',
                'reference_no' => 'FAKE',
            ]);

            $payment->assertOk()
                ->assertJsonPath('payment_status', 'initiated')
                ->assertJsonPath('amount', '99.00')
                ->assertJsonPath('merchant_name', 'লাল কয়েন');
            $this->assertStringContainsString('/payment/start?', (string) $payment->json('url'));
            $this->assertStringContainsString('amount=99.00', (string) $payment->json('url'));
            $this->assertStringContainsString('method='.$method, (string) $payment->json('url'));
            $this->assertStringNotContainsString('Hacker', (string) $payment->json('url'));
        }

        $lalcoin->refresh();
        $this->assertSame('initiated', $lalcoin->payment_status);
        $this->assertSame('99.00', $lalcoin->payment_amount);
        $this->assertSame('upay', $lalcoin->payment_method);

        $job = $this->submit('job-application', [
            'education' => 'bachelor',
            'courier_help' => 'yes',
            'nearest_courier' => '99',
        ]);
        $jobPayment = $this->initialize($job, 'bkash', ['amount' => '20']);
        $jobPayment->assertOk()->assertJsonPath('amount', '99.00');
        $this->assertSame(config('app.name'), $jobPayment->json('merchant_name'));

        $shohoj = $this->submit('shohojcash', [
            'fatherName' => 'Father Name',
            'motherName' => 'Mother Name',
            'dob' => '1995-01-01',
            'nid' => '1234567890',
            'division' => 'ঢাকা',
            'district' => 'Dhaka',
            'thana' => 'Uttara',
            'road' => 'Road 10',
            'loanType' => 'ব্যক্তিগত ঋণ',
            'loanAmount' => 50000,
            'duration' => 6,
            'source' => 'shohojcash.online',
            'consent' => true,
        ]);
        $shohojPayment = $this->initialize($shohoj, 'nagad', ['amount' => '50000']);
        $shohojPayment->assertOk()->assertJsonPath('amount', '20.00');
        $this->assertStringContainsString('amount=20.00', (string) $shohojPayment->json('url'));
    }

    public function test_a_valid_url_is_reused_and_an_expired_url_is_replaced(): void
    {
        $this->assertTrue(Route::has('payment.start'));

        $application = $this->submit('lalcoin', [
            'coin_count' => '10',
            'coin_year' => '2024',
        ]);

        $first = $this->initialize($application, 'nagad')->assertOk();
        $url = (string) $first->json('url');
        $expires = $this->expiresAt($url);

        $this->assertEqualsWithDelta(now()->addMinutes(SignedPaymentUrl::VALID_MINUTES)->getTimestamp(), $expires, 5);

        $this->get($url)->assertRedirect(route('apay-payment.steps.account-number'));

        $second = $this->initialize($application, 'nagad')->assertOk();
        $this->assertSame($url, $second->json('url'));
        $application->refresh();
        $this->assertSame($url, $application->payment_url);

        $this->travel(SignedPaymentUrl::VALID_MINUTES + 1)->minutes();

        $this->get($url)->assertForbidden();

        $third = $this->initialize($application, 'nagad')->assertOk();
        $this->assertNotSame($url, $third->json('url'));
        $this->assertGreaterThan($expires, $this->expiresAt((string) $third->json('url')));
        $this->get($third->json('url'))->assertRedirect(route('apay-payment.steps.account-number'));
    }

    public function test_payment_initialization_is_limited_to_the_submitting_session(): void
    {
        $response = $this->postJson('/submit-form', [
            'form_slug' => 'lalcoin',
            'name' => 'Karim',
            'phone' => '01710000000',
            'address' => 'Dhaka',
            'form_data' => [
                'coin_count' => '10',
                'coin_year' => '2024',
            ],
        ])->assertOk();

        session()->flush();
        session()->save();

        $this->postJson('/init-payment', [
            'application_id' => $response->json('application_id'),
            'method' => 'nagad',
        ])->assertForbidden();

        $application = Application::query()->findOrFail($response->json('application_id'));
        $this->assertNull($application->payment_url);
    }

    public function test_a_completed_payment_is_not_initialized_again(): void
    {
        $application = $this->submit('lalcoin', [
            'coin_count' => '10',
            'coin_year' => '2024',
        ]);
        $first = $this->initialize($application, 'nagad')->assertOk();
        $application->update(['payment_status' => 'paid']);

        $this->initialize($application, 'nagad')->assertStatus(422);
        $application->refresh();
        $this->assertSame($first->json('url'), $application->payment_url);
        $this->assertSame('paid', $application->payment_status);
    }

    public function test_a_busy_initialization_lock_returns_conflict(): void
    {
        $application = $this->submit('lalcoin', [
            'coin_count' => '10',
            'coin_year' => '2024',
        ]);

        $lock = Cache::lock('universal-payment-init:'.$application->id, 30);
        $this->assertTrue($lock->get());

        try {
            $this->initialize($application, 'nagad')->assertStatus(409);
        } finally {
            $lock->release();
        }

        $application->refresh();
        $this->assertNull($application->payment_method);
    }

    public function test_an_unsupported_method_is_rejected(): void
    {
        $application = $this->submit('lalcoin', [
            'coin_count' => '10',
            'coin_year' => '2024',
        ]);

        $this->initialize($application, 'cash')->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function submit(string $slug, array $formData): Application
    {
        $payload = [
            'form_slug' => $slug,
            'name' => 'Karim',
            'phone' => '01710000000',
            'form_data' => $formData,
        ];

        if ($slug !== 'shohojcash') {
            $payload['address'] = 'Dhaka';
        }

        $response = $this->postJson('/submit-form', $payload)->assertOk();
        $this->carrySessionCookies($response);

        return Application::query()->findOrFail($response->json('application_id'));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function initialize(Application $application, string $method, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/init-payment', [
            'application_id' => $application->id,
            'method' => $method,
            ...$extra,
        ]);
    }

    private function expiresAt(string $url): int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (int) $query['expires'];
    }
}
