<?php

namespace App\Events;

use App\Models\Container;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContainerHealthChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Container $container,
        public string $previousStatus,
        public string $newStatus
    ) {}
}
