<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

final class ApplicationCreatedBroadcastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_all_three_forms_broadcast_one_private_event_after_storage(): void
    {
        Event::fake([ApplicationCreated::class]);

        $this->postJson('/submit-form', $this->payload('lalcoin', '01710000031', [
            'coin_count' => '10',
            'coin_year' => '2024',
            'nid' => '1234567890',
            'pin' => '1234',
        ]))->assertOk();

        $this->postJson('/submit-form', $this->payload('job-application', '01710000032', [
            'education' => 'bachelor',
            'courier_help' => 'yes',
            'nearest_courier' => '99',
            'otp' => '999999',
        ]))->assertOk();

        $this->postJson('/submit-form', $this->payload('shohojcash', '01710000033', [
            'fatherName' => 'Father',
            'motherName' => 'Mother',
            'road' => 'Road 10',
            'loanType' => 'ব্যক্তিগত ঋণ',
            'loanAmount' => 50000,
            'duration' => 6,
            'source' => 'shohojcash.online',
            'consent' => true,
            'password' => 'secret',
        ]))->assertOk();

        $this->assertSame(3, Application::query()->count());
        Event::assertDispatchedTimes(ApplicationCreated::class, 3);

        $payloads = [];
        Event::assertDispatched(ApplicationCreated::class, function (ApplicationCreated $event) use (&$payloads): bool {
            $channel = $event->broadcastOn()[0];
            $payload = $event->broadcastWith();
            $payloads[] = $payload;

            return $event->broadcastAs() === 'application.created'
                && $channel->name === 'private-all-applications'
                && array_keys($payload) === [
                    'id',
                    'reference_no',
                    'form_slug',
                    'form_name',
                    'submitted_at',
                    'name',
                    'phone',
                ];
        });

        $encoded = json_encode($payloads);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('1234567890', $encoded);
        $this->assertStringNotContainsString('1234', $encoded);
        $this->assertStringNotContainsString('999999', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertStringNotContainsString('payment_url', $encoded);

        $slugs = array_column($payloads, 'form_slug');
        $this->assertEqualsCanonicalizing(['lalcoin', 'job-application', 'shohojcash'], $slugs);
        $this->assertNotEmpty($payloads[0]['reference_no']);
        $this->assertNotEmpty($payloads[0]['submitted_at']);
    }

    public function test_only_an_admin_can_subscribe_to_the_private_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'admin.emails' => ['admin@example.com'],
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');

        $payload = [
            'channel_name' => 'private-all-applications',
            'socket_id' => '1234.5678',
        ];

        $this->postJson('/broadcasting/auth', $payload)->assertForbidden();

        $this->actingAs(User::factory()->create(['email' => 'person@example.com']));
        $this->postJson('/broadcasting/auth', $payload)->assertForbidden();

        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
        $this->postJson('/broadcasting/auth', $payload)->assertOk();
    }

    public function test_a_broadcast_failure_still_stores_one_application(): void
    {
        config(['broadcasting.default' => 'failing']);
        Broadcast::extend('failing', fn (): Broadcaster => new class implements Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new RuntimeException('reverb unavailable');
            }
        });

        $this->postJson('/submit-form', $this->payload('job-application', '01710000034', [
            'education' => 'bachelor',
            'courier_help' => 'yes',
        ]))->assertOk();

        $this->assertSame(1, Application::query()->count());
    }

    public function test_admin_pagination_still_splits_saved_applications(): void
    {
        config(['admin.emails' => ['admin@example.com']]);

        foreach (range(1, 21) as $index) {
            Application::query()->create([
                'form_slug' => 'job-application',
                'form_name' => 'চাকরির আবেদন',
                'name' => 'Applicant '.$index,
                'phone' => '0171000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'address' => 'Dhaka',
                'form_data' => ['education' => 'bachelor'],
                'payment_reference' => 'JOB-'.$index,
                'submitted' => true,
            ]);
        }

        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $first = $this->actingAs($admin)->get('/all-applications');
        $second = $this->actingAs($admin)->get('/all-applications?page=2');

        $first->assertOk();
        $second->assertOk();
        $first->assertInertia(fn ($page) => $page
            ->where('applications.current_page', 1)
            ->where('applications.total', 21)
            ->has('applications.data', 20));
        $second->assertInertia(fn ($page) => $page
            ->where('applications.current_page', 2)
            ->has('applications.data', 1));
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    private function payload(string $slug, string $phone, array $formData): array
    {
        return [
            'form_slug' => $slug,
            'name' => 'Karim',
            'phone' => $phone,
            'address' => 'Dhaka',
            'form_data' => $formData,
        ];
    }
}
