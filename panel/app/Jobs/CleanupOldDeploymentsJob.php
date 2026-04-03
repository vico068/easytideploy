<?php

namespace App\Jobs;

use App\Models\ApplicationLog;
use App\Models\Deployment;
use App\Models\ResourceUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupOldDeploymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(): void
    {
        // Delete old deployments (keep last 50 per application)
        $this->cleanupDeployments();

        // Delete old logs (older than 7 days)
        $this->cleanupLogs();

        // Delete old metrics (older than 30 days)
        $this->cleanupMetrics();
    }

    private function cleanupDeployments(): void
    {
        $applicationsWithManyDeployments = Deployment::selectRaw('application_id, COUNT(*) as count')
            ->groupBy('application_id')
            ->having('count', '>', 50)
            ->pluck('application_id');

        foreach ($applicationsWithManyDeployments as $applicationId) {
            $deploymentsToKeep = Deployment::where('application_id', $applicationId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->pluck('id');

            Deployment::where('application_id', $applicationId)
                ->whereNotIn('id', $deploymentsToKeep)
                ->delete();
        }
    }

    private function cleanupLogs(): void
    {
        ApplicationLog::where('timestamp', '<', now()->subDays(7))->delete();
    }

    private function cleanupMetrics(): void
    {
        ResourceUsage::where('recorded_at', '<', now()->subDays(30))->delete();
    }
}
