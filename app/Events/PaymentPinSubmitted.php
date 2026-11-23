<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPinSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('applications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.pin.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->application->id,
            'name' => $this->application->name,
            'message' => 'Payment PIN দেওয়া হয়েছে',
        ];
    }
}
