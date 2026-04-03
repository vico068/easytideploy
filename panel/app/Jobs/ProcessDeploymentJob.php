<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\DeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes

    public function __construct(
        public Application $application,
        public ?string $commitSha = null,
        public string $triggeredBy = 'manual'
    ) {}

    public function handle(DeploymentService $deploymentService): void
    {
        $deploymentService->trigger(
            $this->application,
            $this->commitSha,
            $this->triggeredBy
        );
    }

    public function failed(\Throwable $exception): void
    {
        logger()->error("Deployment job failed for application {$this->application->id}: {$exception->getMessage()}");
    }
}
