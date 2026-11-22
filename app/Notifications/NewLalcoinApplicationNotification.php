<?php

namespace App\Notifications;

use App\Models\LalcoinApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLalcoinApplicationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LalcoinApplication $application
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('নতুন Lalcoin Application')
            ->greeting('নতুন Lalcoin Application জমা হয়েছে')
            ->line('নাম: ' . $this->application->name)
            ->line('ঠিকানা: ' . $this->application->address)
            ->line('ফোন: ' . $this->application->phone)
            ->line('Coin Count: ' . $this->application->coin_count)
            ->line('Coin Year: ' . $this->application->coin_year)
            ->line('Application ID: ' . $this->application->id)
            ->line('সময়: ' . $this->application->created_at);
    }

}
