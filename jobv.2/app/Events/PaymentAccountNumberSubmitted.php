<?php

namespace App\Events;

use App\Models\JobApplication;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentAccountNumberSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JobApplication $application
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('applications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.account.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->application->id,
            'name' => $this->application->name,
            'message' => 'Payment account number দেওয়া হয়েছে',
        ];
    }
}
