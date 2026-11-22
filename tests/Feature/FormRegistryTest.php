<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\JobApplication;
use App\Models\LalcoinApplication;
use App\Models\User;
use App\Modules\Forms\Exceptions\InvalidFormConfigurationException;
use App\Modules\Forms\FormConfigurationValidator;
use App\Modules\Forms\FormRegistry;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class FormRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_discovery_registers_enabled_forms_and_skips_the_disabled_example(): void
    {
        $forms = app(FormRegistry::class);

        $this->assertSame(
            ['education-loan'],
            array_values(array_diff(
                ['lalcoin', 'job-application', 'shohojcash', 'education-loan'],
                array_keys($forms->all()),
            )),
        );

        $this->assertNotNull($forms->find('lalcoin'));
        $this->assertNotNull($forms->find('job-application'));
        $this->assertNotNull($forms->find('shohojcash'));
        $this->assertNull($forms->find('education-loan'));
        $this->get('/forms/education-loan')->assertNotFound();
        $this->get('/forms/lalcoin')->assertOk();
    }

    public function test_duplicate_slugs_are_rejected(): void
    {
        $directory = $this->configurationDirectory();
        $config = $this->validConfig('alpha');
        file_put_contents($directory.'/Alpha.php', '<?php return '.var_export($config, true).';');
        file_put_contents($directory.'/Beta.php', '<?php return '.var_export($config, true).';');

        $this->expectException(InvalidFormConfigurationException::class);
        $this->expectExceptionMessage('Duplicate form slug');

        (new FormRegistry(new FormConfigurationValidator, $directory))->all();
    }

    public function test_invalid_configuration_reports_the_file(): void
    {
        $directory = $this->configurationDirectory();
        file_put_contents($directory.'/Broken.php', '<?php return ["slug" => "broken"];');

        $this->expectException(InvalidFormConfigurationException::class);
        $this->expectExceptionMessage('Missing [form_name]');

        (new FormRegistry(new FormConfigurationValidator, $directory))->all();
    }

    public function test_cache_refresh_stores_only_enabled_forms(): void
    {
        config(['forms.cache' => true]);
        Cache::flush();

        $forms = app(FormRegistry::class);
        $forms->refresh();

        $cached = Cache::get(config('forms.cache_key'));

        $this->assertIsArray($cached);
        $this->assertArrayHasKey('lalcoin', $cached);
        $this->assertArrayNotHasKey('education-loan', $cached);

        $this->artisan('forms:clear')->assertSuccessful();
        $this->assertFalse(Cache::has(config('forms.cache_key')));

        $this->artisan('forms:cache')->assertSuccessful();
        $this->assertTrue(Cache::has(config('forms.cache_key')));
    }

    public function test_lalcoin_submission_uses_the_fixed_server_fee(): void
    {
        Notification::fake();

        $response = $this->postJson('/submit-form', [
            'slug' => 'lalcoin',
            'name' => 'Karim',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'payment_amount' => '1.00',
            'merchant_name' => 'Hacker',
            'payment_status' => 'paid',
            'form_data' => [
                'coin_count' => '100',
                'coin_year' => '2026',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'application_id', 'reference_no']);

        $application = Application::query()->first();
        $this->assertNotNull($application);
        $this->assertSame('lalcoin', $application->form_slug);
        $this->assertSame(['coin_count' => '100', 'coin_year' => '2026'], $application->form_data);
        $this->assertSame('99.00', $application->payment_amount);
        $this->assertSame('লাল কয়েন', $application->merchant_name);
        $this->assertNull($application->payment_status);
        $this->assertStringStartsWith('LAL-', (string) $application->payment_reference);
        $this->assertSame($application->id, $response->json('application_id'));
        $this->assertSame($application->payment_reference, $response->json('reference_no'));
        $this->assertSame(0, LalcoinApplication::query()->count());
        $this->carrySessionCookies($response);

        $payment = $this->postJson('/init-payment', [
            'application_id' => $application->id,
            'method' => 'nagad',
            'amount' => '1.00',
            'merchant_name' => 'Hacker',
        ]);

        $payment->assertOk();
        $this->assertStringContainsString('amount=99.00', $payment->json('url'));
        $this->assertSame($application->payment_reference, $payment->json('reference_no'));

        $application->refresh();
        $this->assertSame('99.00', $application->payment_amount);
        $this->assertSame('লাল কয়েন', $application->merchant_name);

        Notification::assertSentOnDemand(ApplicationSubmittedNotification::class);
    }

    public function test_job_fee_must_match_the_server_allow_list(): void
    {
        $rejected = $this->postJson('/submit-form', [
            'slug' => 'job-application',
            'name' => 'Karim',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'form_data' => [
                'education' => 'bachelor',
                'courier_help' => 'yes',
                'nearest_courier' => '5',
            ],
        ]);

        $rejected->assertStatus(422);
        $this->assertSame(0, Application::query()->count());
        $this->assertSame(0, JobApplication::query()->count());

        $accepted = $this->postJson('/submit-form', [
            'slug' => 'job-application',
            'name' => 'Karim',
            'phone' => '01711111112',
            'address' => 'Dhaka',
            'payment_amount' => '1',
            'form_data' => [
                'father_or_husband' => 'Father Name',
                'mother' => 'Mother Name',
                'education' => 'bachelor',
                'courier_help' => 'yes',
                'nearest_courier' => '20',
            ],
        ]);

        $accepted->assertOk()
            ->assertJsonStructure(['success', 'message', 'application_id', 'reference_no']);

        $application = Application::query()->firstOrFail();
        $this->assertSame('20.00', $application->payment_amount);
        $this->assertSame(config('app.name'), $application->merchant_name);
        $this->assertStringStartsWith('JOB-', (string) $application->payment_reference);
        $this->assertSame($application->payment_reference, $accepted->json('reference_no'));
        $this->carrySessionCookies($accepted);
        $payment = $this->postJson('/init-payment', [
            'application_id' => $application->id,
            'method' => 'bkash',
            'amount' => '5000',
        ]);

        $payment->assertOk();
        $this->assertSame('20.00', $payment->json('amount'));
        $this->assertSame($application->payment_reference, $payment->json('reference_no'));
        $this->assertSame(config('app.name'), $payment->json('merchant_name'));
    }

    public function test_shohojcash_composes_address_and_does_not_charge_the_loan_amount(): void
    {
        $response = $this->postJson('/submit-form', [
            'slug' => 'shohojcash',
            'name' => 'Rahim',
            'phone' => '01822222222',
            'form_data' => [
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
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'application_id', 'reference_no']);

        $application = Application::query()->firstOrFail();
        $this->assertSame('Road 10, Uttara, Dhaka, ঢাকা', $application->address);
        $this->assertSame('20.00', $application->payment_amount);
        $this->assertSame(config('shohojcash.merchant_name'), $application->merchant_name);
        $this->assertStringStartsWith('LOAN-', (string) $application->payment_reference);
        $this->assertSame($application->payment_reference, $response->json('reference_no'));
        $this->carrySessionCookies($response);
        $this->assertSame(50000, $application->form_data['loanAmount']);
        $this->assertArrayNotHasKey('consent', $application->form_data);
        $this->assertSame('shohojcash.online', $application->form_data['source']);

        $payment = $this->postJson('/init-payment', [
            'application_id' => $application->id,
            'method' => 'nagad',
            'amount' => '50000',
        ]);

        $payment->assertOk();
        $this->assertSame('20.00', $payment->json('amount'));
        $this->assertSame($application->payment_reference, $payment->json('reference_no'));
        $this->assertSame(0, LalcoinApplication::query()->count());
    }

    public function test_admin_list_requires_authentication(): void
    {
        $this->get('/all-applications')->assertRedirect();

        $this->actingAs(User::factory()->create());
        $this->get('/all-applications')->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function validConfig(string $slug): array
    {
        return [
            'slug' => $slug,
            'form_name' => 'Alpha',
            'description' => 'Example',
            'enabled' => true,
            'reference_prefix' => 'ALP',
            'merchant_name' => 'Alpha',
            'fields' => [],
            'validation_rules' => [],
            'payment_amount' => '10.00',
            'payment_amount_source' => ['type' => 'fixed'],
            'accepted_payment_methods' => ['nagad'],
            'merchant_notification_emails' => [],
            'admin_notification_email' => null,
            'settings' => [],
        ];
    }

    private function configurationDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'forms-'.uniqid();
        mkdir($directory);

        return $directory;
    }
}
