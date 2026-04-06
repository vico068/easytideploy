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
    public readonly ?string $applicationId;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $status,
        public readonly ?string $error = null,
        ?string $userId = null,
    ) {
        // Resolve campos do deployment uma vez só para evitar queries duplicadas
        $deployment = \App\Models\Deployment::with('application')->find($deploymentId);
        $this->userId = $userId ?? $deployment?->application?->user_id;
        $this->applicationId = $deployment?->application_id;
    }

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('deployment.' . $this->deploymentId)];

        if ($this->userId) {
            $channels[] = new PrivateChannel('user.' . $this->userId);
        }

        // Canal da aplicação: usado pelo DeploymentsRelationManager na página de edição
        if ($this->applicationId) {
            $channels[] = new PrivateChannel('application.' . $this->applicationId);
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
