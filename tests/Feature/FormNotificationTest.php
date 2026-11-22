<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Modules\Forms\FormNotificationService;
use App\Modules\Forms\FormRegistry;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\VerifiedPaymentNotification;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

final class FormNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['mail.admin_notification_email' => 'admin@example.com']);
    }

    public function test_admin_and_merchant_recipients_are_deduplicated(): void
    {
        Notification::fake();
        config(['mail.admin_notification_email' => 'Probasiofficecenter@gmail.com']);

        $this->postJson('/submit-form', $this->lalcoin())->assertOk();

        Notification::assertSentOnDemandTimes(ApplicationSubmittedNotification::class, 1);
        Notification::assertSentOnDemand(
            ApplicationSubmittedNotification::class,
            function (ApplicationSubmittedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                $recipients = array_map('strtolower', (array) $notifiable->routes['mail']);
                sort($recipients);

                return $recipients === [
                    'probasiofficecenter@gmail.com',
                    'sarkerhasan498@gmail.com',
                ];
            }
        );
    }

    public function test_job_applications_include_only_the_configured_admin_when_merchants_are_empty(): void
    {
        Notification::fake();

        $this->postJson('/submit-form', $this->job())->assertOk();

        Notification::assertSentOnDemand(
            ApplicationSubmittedNotification::class,
            fn (ApplicationSubmittedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['admin@example.com']
                && $notification->application->form_slug === 'job-application'
        );
    }

    public function test_no_email_is_sent_when_every_recipient_is_missing_or_invalid(): void
    {
        Notification::fake();
        config(['mail.admin_notification_email' => null]);
        $logged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged = $logged || $event->message === 'Form notification had no valid recipients.';
        });

        $this->postJson('/submit-form', $this->job())->assertOk();

        config(['mail.admin_notification_email' => 'not-an-email']);
        $application = Application::query()->firstOrFail();
        app(FormNotificationService::class)->notifySubmission(
            app(FormRegistry::class)->find('job-application'),
            $application,
        );

        Notification::assertNothingSent();
        $this->assertSame(1, Application::query()->count());
        $this->assertTrue($logged);
    }

    public function test_submission_mail_includes_the_application_and_excludes_secrets(): void
    {
        $application = new Application([
            'form_name' => 'লাল কয়েন',
            'name' => 'Karim',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'payment_reference' => 'LAL-1-ABCDEFGH',
            'form_data' => [
                'coin_count' => '10',
                'pin' => '1234',
                'otp_code' => '999999',
                'password' => 'secret',
            ],
        ]);
        $application->created_at = now();

        $lines = (new ApplicationSubmittedNotification($application))
            ->toMail(new AnonymousNotifiable)
            ->introLines;

        $text = implode("\n", $lines);

        $this->assertStringContainsString('লাল কয়েন', $text);
        $this->assertStringContainsString('LAL-1-ABCDEFGH', $text);
        $this->assertStringContainsString('Karim', $text);
        $this->assertStringContainsString('01711111111', $text);
        $this->assertStringContainsString('Dhaka', $text);
        $this->assertStringContainsString('coin_count: 10', $text);
        $this->assertStringNotContainsString('1234', $text);
        $this->assertStringNotContainsString('999999', $text);
        $this->assertStringNotContainsString('secret', $text);
    }

    public function test_mail_failure_keeps_the_application_and_the_realtime_event(): void
    {
        Event::fake([ApplicationCreated::class]);
        config(['mail.default' => 'failing']);
        \Illuminate\Support\Facades\Mail::extend('failing', function () {
            return new class extends \Symfony\Component\Mailer\Transport\AbstractTransport
            {
                protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
                {
                    throw new RuntimeException('smtp down');
                }

                public function __toString(): string
                {
                    return 'failing';
                }
            };
        });

        $this->postJson('/submit-form', $this->job())->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, Application::query()->count());
        Event::assertDispatched(ApplicationCreated::class);
    }

    public function test_repeated_submission_processing_sends_one_email(): void
    {
        Notification::fake();

        $this->postJson('/submit-form', $this->job())->assertOk();
        $application = Application::query()->firstOrFail();
        $form = app(FormRegistry::class)->find('job-application');

        app(FormNotificationService::class)->notifySubmission($form, $application);

        Notification::assertSentOnDemandTimes(ApplicationSubmittedNotification::class, 1);
    }

    public function test_all_three_forms_use_the_same_submission_notification(): void
    {
        Notification::fake();
        Event::fake([ApplicationCreated::class]);

        $this->postJson('/submit-form', $this->lalcoin())->assertOk();
        $this->postJson('/submit-form', $this->job('01710000002'))->assertOk();
        $this->postJson('/submit-form', $this->shohoj())->assertOk();

        Notification::assertSentOnDemandTimes(ApplicationSubmittedNotification::class, 3);
        Event::assertDispatchedTimes(ApplicationCreated::class, 3);

        $slugs = Application::query()->orderBy('id')->pluck('form_slug')->all();
        $this->assertSame(['lalcoin', 'job-application', 'shohojcash'], $slugs);
    }

    public function test_unverified_payment_does_not_send_a_success_email_and_a_verified_callback_is_not_repeated(): void
    {
        Notification::fake();

        $this->postJson('/submit-form', $this->job())->assertOk();
        $application = Application::query()->firstOrFail();
        $service = app(FormNotificationService::class);

        $service->notifyVerifiedPayment($application, false);
        $service->notifyVerifiedPayment($application, true);

        $application->payment_status = 'paid';
        $service->notifyVerifiedPayment($application, false);
        Notification::assertNotSentTo(new AnonymousNotifiable, VerifiedPaymentNotification::class);
        Notification::assertSentOnDemandTimes(VerifiedPaymentNotification::class, 0);

        $service->notifyVerifiedPayment($application, true);
        $service->notifyVerifiedPayment($application, true);

        Notification::assertSentOnDemandTimes(VerifiedPaymentNotification::class, 1);
        Notification::assertSentOnDemand(
            VerifiedPaymentNotification::class,
            function (VerifiedPaymentNotification $notification) use ($application): bool {
                $lines = implode("\n", $notification->toMail(new AnonymousNotifiable)->introLines);

                return $notification->application->is($application)
                    && str_contains($lines, (string) $application->payment_reference)
                    && str_contains($lines, '20.00')
                    && ! str_contains($lines, 'signature');
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function lalcoin(): array
    {
        return [
            'form_slug' => 'lalcoin',
            'name' => 'Karim',
            'phone' => '01710000001',
            'address' => 'Dhaka',
            'form_data' => ['coin_count' => '10', 'coin_year' => '2024'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function job(string $phone = '01710000003'): array
    {
        return [
            'form_slug' => 'job-application',
            'name' => 'Karim',
            'phone' => $phone,
            'address' => 'Dhaka',
            'form_data' => [
                'education' => 'bachelor',
                'courier_help' => 'yes',
                'nearest_courier' => '99',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shohoj(): array
    {
        return [
            'form_slug' => 'shohojcash',
            'name' => 'Rahim',
            'phone' => '01710000004',
            'address' => 'Road 10, Dhaka',
            'form_data' => [
                'fatherName' => 'Father',
                'motherName' => 'Mother',
                'road' => 'Road 10',
                'loanType' => 'ব্যক্তিগত ঋণ',
                'loanAmount' => 50000,
                'duration' => 6,
                'source' => 'shohojcash.online',
                'consent' => true,
            ],
        ];
    }
}
