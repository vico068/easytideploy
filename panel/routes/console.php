<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Metrics collection every minute (backup collector in case orchestrator is down)
Schedule::job(new \App\Jobs\CollectMetricsJob())
    ->everyMinute()
    ->name('collect-metrics')
    ->withoutOverlapping();
