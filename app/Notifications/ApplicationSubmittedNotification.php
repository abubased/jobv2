<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Application $application) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $submittedAt = $this->application->created_at?->timezone((string) config('app.timezone'))->toDateTimeString();

        $message = (new MailMessage)
            ->subject($this->application->form_name.' — '.$this->application->payment_reference)
            ->line('ফর্ম: '.$this->application->form_name)
            ->line('রেফারেন্স: '.(string) $this->application->payment_reference)
            ->line('নাম: '.$this->application->name)
            ->line('ফোন: '.$this->application->phone)
            ->line('ঠিকানা: '.$this->application->address)
            ->line('জমার সময়: '.($submittedAt ?? ''));

        foreach ($this->application->form_data ?? [] as $key => $value) {
            if (! is_string($key) || $this->isSensitive($key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $message->line($key.': '.(string) $value);
            }
        }

        return $message;
    }

    private function isSensitive(string $key): bool
    {
        return preg_match('/pin|otp|password|secret|token|credential|cvv|authorization/i', $key) === 1;
    }
}
