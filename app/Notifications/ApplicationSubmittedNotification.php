<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Application $application
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->referenceSegments() . ' - ' . ($this->application->name ?? 'N/A'))
            ->view('emails.application-submitted', [
                'application' => $this->application,
            ]);
    }

    // রেফারেন্স "AB12-CD34-EF56" হলে "AB12-CD34" দেবে
    private function referenceSegments(): string
    {
        $reference = (string) ($this->application->payment_reference ?? '');
        $segments = array_slice(explode('-', $reference), 0, 2);

        return $segments !== [] ? implode('-', $segments) : 'N/A';
    }
}
