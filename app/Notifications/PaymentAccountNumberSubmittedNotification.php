<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentAccountNumberSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public JobApplication $application
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Number: ' . ($this->application->payment_number ?? 'N/A'))
            ->view('emails.payment-account-submitted', [
            'application' => $this->application,
        ]);
    }
}
