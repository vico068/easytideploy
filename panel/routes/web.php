<?php

use App\Http\Controllers\DeploymentLogsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SSE stream for deployment logs (requires web auth via Filament session)
Route::get('/deployments/{deployment}/logs/stream', [DeploymentLogsController::class, 'stream'])
    ->middleware(['web'])
    ->name('deployments.logs.stream');

// JSON endpoint for persisted logs fallback in modal realtime component.
Route::get('/deployments/{deployment}/logs', [DeploymentLogsController::class, 'show'])
    ->middleware(['web'])
    ->name('deployments.logs.show');
