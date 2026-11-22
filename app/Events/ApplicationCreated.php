<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Application $application) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('all-applications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'application.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->application->id,
            'form_slug' => $this->application->form_slug,
            'form_name' => $this->application->form_name,
            'name' => $this->application->name,
            'phone' => $this->application->phone,
        ];
    }
}
