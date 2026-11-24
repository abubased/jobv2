<?php

namespace App\Notifications;

use App\Models\Application;
use App\Notifications\Concerns\HasPaymentBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PinSubmittedNotification extends Notification
{
    use Queueable, HasPaymentBrand;

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
            ->subject("পিন নাম্বার - {$this->application->name} - {$this->application->id}")
            ->view('emails.pin-submitted', [
                'application' => $this->application,
                'brand' => $this->brandColors(),
                'listUrl' => url('/all-applications'),
            ]);
    }
}
