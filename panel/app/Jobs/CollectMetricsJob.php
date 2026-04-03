<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public ?Application $application = null
    ) {}

    public function handle(MetricsService $metricsService): void
    {
        $applications = $this->application
            ? collect([$this->application])
            : Application::where('status', 'active')->get();

        foreach ($applications as $application) {
            try {
                $metricsService->collectMetrics($application);
            } catch (\Exception $e) {
                logger()->warning("Failed to collect metrics for application {$application->id}: {$e->getMessage()}");
            }
        }
    }
}
