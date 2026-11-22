<?php

namespace App\Events;

use App\Models\JobApplication;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobApplicationCreated implements ShouldBroadcastNow
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
        return 'application.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->application->id,
            'name' => $this->application->name,
            'message' => 'নতুন আবেদন এসেছে',
            'created_at' => $this->application->created_at?->toISOString(),
        ];
    }
}
