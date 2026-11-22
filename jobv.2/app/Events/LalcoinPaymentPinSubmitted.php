<?php

namespace App\Events;

use App\Models\LalcoinApplication;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LalcoinPaymentPinSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LalcoinApplication $application
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('lalcoin-applications'),
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
        ];
    }
}
