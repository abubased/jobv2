<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationCreated implements ShouldBroadcastNow, ShouldRescue
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
            'reference_no' => $this->application->payment_reference,
            'form_slug' => $this->application->form_slug,
            'form_name' => $this->application->form_name,
            'submitted_at' => $this->application->created_at?->toIso8601String(),
            'name' => $this->application->name,
            'phone' => $this->application->phone,
        ];
    }
}
