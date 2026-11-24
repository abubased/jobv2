<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PinSubmitted implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('all-applications'),    // AllApplications পেজের চ্যানেল
        ];
    }

    public function broadcastAs(): string
    {
        return 'pin.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id'      => $this->application->id,
            'name'    => $this->application->name,
            'message' => "{$this->application->name} পিন জমা দিয়েছেন",
        ];
    }
}
