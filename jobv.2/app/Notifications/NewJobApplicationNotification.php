<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewJobApplicationNotification extends Notification
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
            ->subject('New Order-'. $this->application->phone)
            ->view('emails.account-submitted', [
            'application' => $this->application,
        ]);
    }
}
