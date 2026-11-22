<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifiedPaymentNotification extends Notification
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
        return (new MailMessage)
            ->subject('পেমেন্ট যাচাই হয়েছে — '.$this->application->payment_reference)
            ->line('ফর্ম: '.$this->application->form_name)
            ->line('রেফারেন্স: '.(string) $this->application->payment_reference)
            ->line('পেমেন্ট মাধ্যম: '.(string) $this->application->payment_method)
            ->line('যাচাইকৃত ফি: '.(string) $this->application->payment_amount)
            ->line('পেমেন্ট অবস্থা: '.(string) $this->application->payment_status);
    }
}
