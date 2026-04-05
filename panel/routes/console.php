<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Metrics collection is handled by the orchestrator's Go metrics collector (every 30s).
// The PHP CollectMetricsJob was removed because it created duplicate rows with zeros
// (it reads from DB via orchestrator HTTP, not from Docker directly).

// Cleanup old deployments, logs, and metrics daily at 3 AM
Schedule::job(new \App\Jobs\CleanupOldDeploymentsJob())
    ->dailyAt('03:00')
    ->name('cleanup-old-data')
    ->withoutOverlapping();
