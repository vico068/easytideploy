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

    public readonly ?string $userId;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $status,
        public readonly ?string $error = null,
        ?string $userId = null,
    ) {
        // Resolve userId for the user-level channel (dashboard, lists, widgets)
        $this->userId = $userId ?? \App\Models\Deployment::with('application')
            ->find($deploymentId)
            ?->application
            ?->user_id;
    }

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('deployment.' . $this->deploymentId)];

        if ($this->userId) {
            $channels[] = new PrivateChannel('user.' . $this->userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'DeploymentStatusChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'deployment_id' => $this->deploymentId,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}
