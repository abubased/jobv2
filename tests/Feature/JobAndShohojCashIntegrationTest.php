<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\JobApplication;
use App\Models\LalcoinApplication;
use Tests\TestCase;

final class JobAndShohojCashIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_job_application_uses_the_universal_api_and_a_fixed_fee(): void
    {
        $first = $this->postJson('/submit-form', $this->jobPayload('01710000021'))->assertOk();
        $second = $this->postJson('/submit-form', $this->jobPayload('01710000021'))->assertOk();

        $this->assertNotSame($first->json('reference_no'), $second->json('reference_no'));
        $this->assertSame(2, Application::query()->where('phone', '01710000021')->count());
        $this->assertSame(0, JobApplication::query()->count());

        $application = Application::query()->firstOrFail();
        $this->assertSame('job-application', $application->form_slug);
        $this->assertSame('চাকরির আবেদন', $application->form_name);
        $this->assertSame('Dhaka', $application->address);
        $this->assertSame('99', $application->form_data['nearest_courier']);
        $this->assertSame('20.00', $application->payment_amount);
        $this->assertStringStartsWith('JOB-', (string) $application->payment_reference);
        $this->assertNotSame('paid', $application->payment_status);

        $this->carrySessionCookies($first);

        $payment = $this->postJson('/init-payment', [
            'application_id' => $application->id,
            'method' => 'nagad',
            'amount' => '500',
        ])->assertOk();

        $this->assertSame('20.00', $payment->json('amount'));
        $this->assertNotSame('paid', $payment->json('payment_status'));
    }

    public function test_shohojcash_keeps_the_loan_amount_out_of_the_fee(): void
    {
        $response = $this->postJson('/submit-form', [
            'form_slug' => 'shohojcash',
            'name' => 'Rahim',
            'phone' => '01710000022',
            'address' => 'Road 10, Uttara, Dhaka, ঢাকা',
            'payment_amount' => '50000',
            'form_data' => [
                'fatherName' => 'Father',
                'motherName' => 'Mother',
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
            ],
        ])->assertOk();

        $application = Application::query()->firstOrFail();
        $this->assertSame('shohojcash', $application->form_slug);
        $this->assertSame('সহজ ক্যাশ', $application->merchant_name);
        $this->assertSame(50000, $application->form_data['loanAmount']);
        $this->assertSame('20.00', $application->payment_amount);
        $this->assertStringStartsWith('LOAN-', (string) $application->payment_reference);
        $this->assertSame($application->id, $response->json('application_id'));
        $this->assertSame(0, LalcoinApplication::query()->count());

        $this->carrySessionCookies($response);

        $this->postJson('/init-payment', [
            'application_id' => $application->id,
            'method' => 'bkash',
            'amount' => '50000',
        ])->assertOk()->assertJsonPath('amount', '20.00');
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(string $phone): array
    {
        return [
            'form_slug' => 'job-application',
            'name' => 'Karim',
            'phone' => $phone,
            'address' => 'Dhaka',
            'payment_amount' => '99',
            'form_data' => [
                'father_or_husband' => 'Father',
                'mother' => 'Mother',
                'education' => 'bachelor',
                'courier_help' => 'yes',
                'nearest_courier' => '99',
            ],
        ];
    }
}
