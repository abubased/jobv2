<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Tests\TestCase;

final class AllApplicationsAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['admin.emails' => ['admin@example.com']]);
    }

    public function test_only_admins_can_open_the_list_and_detail(): void
    {
        $application = $this->application('JOB-1', 'Karim', '01710000041');

        $this->get('/all-applications')->assertRedirect();
        $this->get('/all-applications/'.$application->id)->assertRedirect();

        $this->actingAs(User::factory()->create(['email' => 'person@example.com']));
        $this->get('/all-applications')->assertForbidden();
        $this->get('/all-applications/'.$application->id)->assertForbidden();

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        $this->get('/all-applications')->assertOk();
        $this->get('/all-applications/'.$application->id)->assertOk();
    }

    public function test_search_filters_and_pagination_stay_on_the_server(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $match = $this->application('JOB-FIND-1', 'Rahim Ahmed', '01710000051', 'job-application', 'চাকরির আবেদন', 'initiated');
        $this->application('LOAN-OTHER', 'Karim', '01810000052', 'shohojcash', 'সহজ ক্যাশ', null);
        $match->forceFill(['created_at' => '2026-11-01 10:00:00'])->save();

        foreach (range(1, 20) as $index) {
            $this->application('JOB-PAGE-'.$index, 'Other '.$index, '0191000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT));
        }

        $this->actingAs($admin)
            ->get('/all-applications?reference=FIND&name=Rahim&phone=01710000051&form=job-application&payment_status=initiated&submitted_from=2026-11-01&submitted_to=2026-11-01')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('applications.total', 1)
                ->where('applications.data.0.payment_reference', 'JOB-FIND-1')
                ->missing('applications.data.0.form_data')
                ->missing('applications.data.0.payment_url')
                ->has('forms'));

        $this->actingAs($admin)
            ->get('/all-applications?form=shohojcash&page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('applications.total', 1));
    }

    public function test_detail_shows_form_data_and_recorded_value_origins(): void
    {
        $response = $this->postJson('/submit-form', [
            'form_slug' => 'shohojcash',
            'name' => 'Rahim',
            'phone' => '01710000061',
            'address' => 'Road 10, Dhaka',
            'form_data' => [
                'fatherName' => 'Father',
                'motherName' => 'Mother',
                'road' => '<script>alert(1)</script>',
                'loanType' => 'ব্যক্তিগত ঋণ',
                'loanAmount' => 50000,
                'duration' => 6,
                'source' => 'shohojcash.online',
                'consent' => true,
            ],
        ])->assertOk();

        $application = Application::query()->findOrFail($response->json('application_id'));
        $this->assertSame('applicant', $application->form_field_sources['road']);
        $this->assertSame('backend', $application->form_field_sources['source']);
        $this->assertSame('shohojcash.online', $application->form_data['source']);

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $detail = $this->actingAs($admin)->get('/all-applications/'.$application->id);

        $detail->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('application.form_name', 'সহজ ক্যাশ')
                ->where('application.form_data.road', '<script>alert(1)</script>')
                ->where('application.form_data.loanAmount', 50000)
                ->where('application.field_sources.road', 'applicant')
                ->where('application.field_sources.source', 'backend')
                ->missing('application.payment_url'));

        $detail->assertDontSee('<script>alert(1)</script>', false);

        $older = $this->application('OLD-1', 'Karim', '01710000062');
        $older->forceFill(['form_field_sources' => null])->save();

        $this->actingAs($admin)
            ->get('/all-applications/'.$older->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('application.field_sources', null));
    }

    private function application(
        string $reference,
        string $name,
        string $phone,
        string $slug = 'job-application',
        string $formName = 'চাকরির আবেদন',
        ?string $paymentStatus = null,
    ): Application {
        return Application::query()->create([
            'form_slug' => $slug,
            'form_name' => $formName,
            'name' => $name,
            'phone' => $phone,
            'address' => 'Dhaka',
            'form_data' => ['note' => 'stored'],
            'payment_reference' => $reference,
            'payment_status' => $paymentStatus,
            'payment_amount' => '20.00',
            'submitted' => true,
        ]);
    }
}
