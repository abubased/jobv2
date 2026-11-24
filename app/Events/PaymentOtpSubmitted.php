<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentOtpSubmitted implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('all-applications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'otp.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->application->id,
            'name' => $this->application->name,
            'message' => 'Payment OTP দেওয়া হয়েছে',
        ];
    }
}
