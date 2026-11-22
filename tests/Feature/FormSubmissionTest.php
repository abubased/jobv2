<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Models\JobApplication;
use App\Models\LalcoinApplication;
use App\Modules\Forms\FormConfigurationValidator;
use App\Modules\Forms\FormRegistry;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class FormSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_unknown_and_disabled_forms_are_rejected(): void
    {
        $this->postJson('/submit-form', $this->common('missing-form'))
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->postJson('/submit-form', $this->common('education-loan'))
            ->assertNotFound();

        $this->assertSame(0, Application::query()->count());
    }

    public function test_conflicting_form_identifiers_are_rejected(): void
    {
        $this->postJson('/submit-form', [
            ...$this->common('lalcoin'),
            'slug' => 'job-application',
            'form_data' => ['coin_count' => '10', 'coin_year' => '2024'],
        ])->assertStatus(422)->assertJsonValidationErrors(['form_slug']);

        $this->assertSame(0, Application::query()->count());
    }

    public function test_the_same_phone_can_submit_more_than_once(): void
    {
        Notification::fake();
        Event::fake([ApplicationCreated::class]);

        $payload = [
            ...$this->common('lalcoin'),
            'form_data' => ['coin_count' => '10', 'coin_year' => '2024'],
        ];

        $first = $this->postJson('/submit-form', $payload)->assertOk();
        $second = $this->postJson('/submit-form', $payload)->assertOk();

        $this->assertSame(2, Application::query()->where('phone', '01711111111')->count());
        $this->assertNotSame($first->json('reference_no'), $second->json('reference_no'));
        Event::assertDispatchedTimes(ApplicationCreated::class, 2);
        Notification::assertSentOnDemandTimes(ApplicationSubmittedNotification::class, 2);
        $this->assertSame(0, LalcoinApplication::query()->count());
        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_a_sample_form_uses_the_same_endpoint_and_dependent_rules(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'forms-'.uniqid();
        mkdir($directory);
        file_put_contents($directory.'/Sample.php', '<?php return '.var_export($this->sampleConfig(), true).';');

        $this->app->instance(FormRegistry::class, new FormRegistry(new FormConfigurationValidator, $directory));

        $missing = $this->postJson('/submit-form', [
            ...$this->common('sample-form'),
            'payment_amount' => '1.00',
            'merchant_name' => 'Hacker',
            'payment_reference' => 'FAKE-1',
            'payment_status' => 'paid',
            'form_data' => ['purpose' => 'education'],
        ]);

        $missing->assertStatus(422)->assertJsonValidationErrors(['form_data.institution']);
        $this->assertSame(0, Application::query()->count());

        $stored = $this->postJson('/submit-form', [
            ...$this->common('sample-form'),
            'payment_amount' => '1.00',
            'merchant_name' => 'Hacker',
            'form_data' => [
                'purpose' => 'personal',
                'institution' => 'Hidden School',
            ],
        ]);

        $stored->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'application_id', 'reference_no']);

        $application = Application::query()->firstOrFail();
        $this->assertSame('sample-form', $application->form_slug);
        $this->assertSame(['purpose' => 'personal'], $application->form_data);
        $this->assertSame('15.00', $application->payment_amount);
        $this->assertSame('Sample Merchant', $application->merchant_name);
        $this->assertNull($application->payment_status);
        $this->assertStringStartsWith('SMP-', (string) $application->payment_reference);
        $this->assertSame($application->id, $stored->json('application_id'));
        $this->assertSame($application->payment_reference, $stored->json('reference_no'));

        $this->postJson('/submit-form', [
            ...$this->common('sample-form'),
            'phone' => '01711111112',
            'form_data' => [
                'purpose' => 'education',
                'institution' => 'Dhaka College',
            ],
        ])->assertOk();

        $education = Application::query()->where('phone', '01711111112')->firstOrFail();
        $this->assertSame('Dhaka College', $education->form_data['institution']);
    }

    /**
     * @return array<string, mixed>
     */
    private function common(string $slug): array
    {
        return [
            'form_slug' => $slug,
            'name' => 'Karim',
            'phone' => '01711111111',
            'address' => 'Dhaka',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleConfig(): array
    {
        return [
            'slug' => 'sample-form',
            'form_name' => 'Sample Form',
            'description' => 'Dependent field example',
            'enabled' => true,
            'reference_prefix' => 'SMP',
            'merchant_name' => 'Sample Merchant',
            'fields' => [
                [
                    'name' => 'purpose',
                    'label' => 'Purpose',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'education', 'label' => 'Education'],
                        ['value' => 'personal', 'label' => 'Personal'],
                    ],
                    'validation' => ['required', 'in:education,personal'],
                ],
                [
                    'name' => 'institution',
                    'label' => 'Institution',
                    'type' => 'text',
                    'required' => true,
                    'depends_on' => ['field' => 'purpose', 'values' => ['education']],
                    'validation' => ['string', 'max:255'],
                ],
            ],
            'validation_rules' => [],
            'payment_amount' => '15.00',
            'payment_amount_source' => ['type' => 'fixed'],
            'accepted_payment_methods' => ['nagad'],
            'merchant_notification_emails' => [],
            'admin_notification_email' => null,
            'settings' => [],
        ];
    }
}
