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
        $message = (new MailMessage)
            ->subject($this->application->form_name.' — '.$this->application->phone)
            ->line('ফর্ম: '.$this->application->form_name)
            ->line('নাম: '.$this->application->name)
            ->line('ফোন: '.$this->application->phone)
            ->line('ঠিকানা: '.$this->application->address)
            ->line('আবেদন নম্বর: '.$this->application->id);

        foreach ($this->application->form_data ?? [] as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $message->line($key.': '.(string) $value);
            }
        }

        return $message;
    }
}
