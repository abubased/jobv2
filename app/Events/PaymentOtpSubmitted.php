<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PaymentOtpSubmitted implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, SerializesModels;

    private string $eventId;

    public function __construct(public Application $application)
    {
        $this->eventId = (string) Str::uuid();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('all-applications')];
    }

    public function broadcastAs(): string
    {
        return 'otp.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => 'otp_submitted',
            'change_aliases' => ['box_number_updated'],
            'updated_fields' => ['box-no', 'product_countdown_at'],
            'id' => (int) $this->application->id,
            'name' => $this->application->name,
            'message' => 'OTP submitted / Box number updated',
            'application' => $this->application->toDashboardPayload(),
        ];
    }
}
