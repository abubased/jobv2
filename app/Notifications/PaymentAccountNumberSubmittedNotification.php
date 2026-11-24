<?php

namespace App\Notifications;

use App\Models\Application;
use App\Notifications\Concerns\HasPaymentBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PaymentAccountNumberSubmittedNotification extends Notification
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
            ->subject($this->paymentMethodLabel() . ' - ' . ($this->applicantNumber() ?? 'N/A'))
            ->view('emails.payment-account-submitted', [
                'application' => $this->application,
                'brand' => $this->brandColors(),
                'methodLabel' => $this->paymentMethodLabel(),
                'methodNumber' => $this->applicantNumber(),
                'detailUrl' => url("/all-applications/{$this->application->id}"),
                'deleteUrl' => URL::temporarySignedRoute(
                    'applications.delete-confirm',
                    now()->addDays(7),
                    ['application' => $this->application->id]
                ),
            ]);
    }
}
