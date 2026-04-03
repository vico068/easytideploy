<?php

namespace App\Events;

use App\Models\Deployment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Deployment $deployment
    ) {}
}
