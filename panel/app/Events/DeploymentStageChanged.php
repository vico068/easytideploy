<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStageChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $stage,
        public readonly string $status,
        public readonly string $ts,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('deployment.' . $this->deploymentId);
    }

    public function broadcastAs(): string
    {
        return 'DeploymentStageChanged';
    }

    public function broadcastWith(): array
    {
        return ['stage' => $this->stage, 'status' => $this->status, 'ts' => $this->ts];
    }
}
