<?php

namespace App\Events;

use App\Models\LalcoinApplication;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LalcoinApplicationCreated implements ShouldBroadcastNow
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
        return 'application.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->application->id,
            'name' => $this->application->name,
            'phone' => $this->application->phone,
        ];
    }

}
