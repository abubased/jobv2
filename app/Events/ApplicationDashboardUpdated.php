<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ApplicationDashboardUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, SerializesModels;

    private string $eventId;

    public function __construct(
        public Application $application,
        public string $changeType,
    ) {
        $this->eventId = (string) Str::uuid();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('all-applications')];
    }

    public function broadcastAs(): string
    {
        return 'application.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->changeType,
            'id' => (int) $this->application->id,
            'message' => 'Application updated',
            'application' => $this->application->toDashboardPayload(),
        ];
    }
}
