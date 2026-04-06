<?php

namespace App\Events;

use App\Models\Container;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContainerStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Container $container) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('application.' . $this->container->application_id);
    }

    public function broadcastAs(): string
    {
        return 'ContainerStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'container_id' => $this->container->id,
            'name' => $this->container->name,
            'status' => $this->container->status,
            'health_status' => $this->container->health_status,
            'cpu_usage' => $this->container->cpu_usage,
            'memory_usage' => $this->container->memory_usage,
        ];
    }
}
