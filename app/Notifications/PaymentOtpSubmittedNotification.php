<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PaymentOtpSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Application $application,
        public string $otp,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('অর্ডার নাম্বার- ' . $this->otp)
            ->view('emails.payment-otp-submitted', [
                'application' => $this->application,
                'otp' => $this->otp,
                'copyUrl' => URL::signedRoute('applications.copy', ['application' => $this->application->id]),
            ]);
    }
}
