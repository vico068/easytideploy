<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuildLogReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $deploymentId,
        public readonly string $line,
        public readonly string $stage,
        public readonly string $ts,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('deployment.' . $this->deploymentId);
    }

    public function broadcastAs(): string
    {
        return 'BuildLogReceived';
    }

    public function broadcastWith(): array
    {
        return ['line' => $this->line, 'stage' => $this->stage, 'ts' => $this->ts];
    }
}
