<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStageChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $stage,
        public readonly string $status,
        public readonly string $ts,
        public readonly ?string $applicationId = null,
        public readonly ?string $userId = null,
    ) {}

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('deployment.' . $this->deploymentId)];

        if ($this->applicationId) {
            $channels[] = new PrivateChannel('application.' . $this->applicationId);
        }

        if ($this->userId) {
            $channels[] = new PrivateChannel('user.' . $this->userId);
        }

        return $channels;
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
