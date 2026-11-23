<?php

declare(strict_types=1);

namespace App\Modules\Forms;

use App\Models\Application;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\VerifiedPaymentNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * One mail workflow for every registered form.
 * Messages are sent immediately because shared hosting has no queue worker.
 */
final class FormNotificationService
{
    public function notifySubmission(FormDefinition $form, Application $application): void
    {
        $this->sendOnce(
            'form-notification:submission:'.$application->id,
            $application,
            $this->recipients($form, $application, 'submission'),
            new ApplicationSubmittedNotification($application),
        );
    }

    public function notifyVerifiedPayment(Application $application, bool $verifiedByProvider): void
    {
        if (! $verifiedByProvider || ! in_array($application->payment_status, ['paid', 'success'], true)) {
            Log::info('Skipped unverified payment notification.', [
                'application_id' => $application->id,
                'payment_status' => $application->payment_status,
            ]);

            return;
        }

        $form = app(FormRegistry::class)->find($application->form_slug);

        if (! $form instanceof FormDefinition) {
            Log::warning('Verified payment notification skipped because the form is unavailable.', [
                'application_id' => $application->id,
                'form_slug' => $application->form_slug,
            ]);

            return;
        }

        $this->sendOnce(
            'form-notification:payment:'.$application->id.':'.$application->payment_status,
            $application,
            $this->recipients($form, $application, 'payment'),
            new VerifiedPaymentNotification($application),
        );
    }

    /**
     * @return list<string>
     */
    private function recipients(FormDefinition $form, Application $application, string $kind): array
    {
        $recipients = [];

        foreach ([config('mail.admin_notification_email'), ...$form->merchantNotificationEmails()] as $email) {
            if (! is_string($email) || trim($email) === '') {
                continue;
            }

            $email = trim($email);

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                Log::warning('Form notification skipped an invalid recipient.', [
                    'application_id' => $application->id,
                    'kind' => $kind,
                ]);

                continue;
            }

            $key = strtolower($email);

            if (! array_key_exists($key, $recipients)) {
                $recipients[$key] = $email;
            }
        }

        if ($recipients === []) {
            Log::warning('Form notification had no valid recipients.', [
                'application_id' => $application->id,
                'form_slug' => $application->form_slug,
                'kind' => $kind,
            ]);
        }

        return array_values($recipients);
    }

    /**
     * @param  list<string>  $recipients
     */
    private function sendOnce(string $claimKey, Application $application, array $recipients, object $notification): void
    {
        if ($recipients === []) {
            return;
        }

        if (! Cache::add($claimKey, true, now()->addYear())) {
            Log::info('Skipped a duplicate form notification.', [
                'application_id' => $application->id,
            ]);

            return;
        }

        try {
            Notification::route('mail', $recipients)->notify($notification);
        } catch (Throwable $exception) {
            Cache::forget($claimKey);

            Log::warning('Form notification could not be sent.', [
                'application_id' => $application->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
