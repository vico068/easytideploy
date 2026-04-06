<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $status,
        public readonly ?string $error = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('deployment.' . $this->deploymentId);
    }

    public function broadcastAs(): string
    {
        return 'DeploymentStatusChanged';
    }

    public function broadcastWith(): array
    {
        return ['status' => $this->status, 'error' => $this->error];
    }
}
