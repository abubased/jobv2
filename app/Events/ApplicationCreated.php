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

class ApplicationCreated implements ShouldBroadcastNow, ShouldRescue
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
        return 'application.created';
    }

    public function broadcastWith(): array
    {
        $row = $this->application->toDashboardPayload();

        return [
            'event_id' => $this->eventId,
            'event_type' => 'created',
            'id' => $row['id'],
            'reference_no' => $row['payment_reference'],
            'form_slug' => $row['form_slug'],
            'form_name' => $row['form_name'],
            'submitted_at' => $row['created_at'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'message' => 'নতুন আবেদন এসেছে',
            'application' => $row,
        ];
    }
}
