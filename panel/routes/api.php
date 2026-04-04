<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Webhooks
Route::prefix('webhooks')->group(function () {
    Route::post('/github/{application}', [WebhookController::class, 'github'])->name('webhooks.github');
    Route::post('/gitlab/{application}', [WebhookController::class, 'gitlab'])->name('webhooks.gitlab');
    Route::post('/bitbucket/{application}', [WebhookController::class, 'bitbucket'])->name('webhooks.bitbucket');
});

// Internal API (called by orchestrator)
// Deployment status callback - secured with orchestrator API key
Route::post('/internal/deployments/{deployment}/status', function ($deployment, \Illuminate\Http\Request $request) {
    $apiKey = $request->bearerToken();
    if (! $apiKey || ! hash_equals(config('easydeploy.orchestrator_api_key') ?? '', $apiKey)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $deployment = \App\Models\Deployment::findOrFail($deployment);
    $deploymentService = app(\App\Services\DeploymentService::class);

    $status = $request->input('status');
    $buildLogs = $request->input('build_logs');

    // Update build logs
    if ($buildLogs) {
        $deployment->update(['build_logs' => $buildLogs]);
    }

    // Handle status transitions
    if ($status === 'running') {
        $deploymentService->markAsRunning($deployment);
    } elseif ($status === 'failed') {
        $reason = $request->input('error_message', 'Deployment failed');
        $deploymentService->markAsFailed($deployment, $reason);
    } else {
        $deployment->update(['status' => $status]);
    }

    return response()->json(['success' => true]);
});

Route::prefix('internal')->middleware('auth:sanctum')->group(function () {
    Route::post('/containers/{container}/status', function ($container, \Illuminate\Http\Request $request) {
        $container = \App\Models\Container::findOrFail($container);
        $container->update([
            'status' => $request->input('status'),
            'health_status' => $request->input('health_status'),
            'cpu_usage' => $request->input('cpu_usage'),
            'memory_usage' => $request->input('memory_usage'),
        ]);

        return response()->json(['success' => true]);
    });

    Route::post('/servers/{server}/heartbeat', function ($server, \Illuminate\Http\Request $request) {
        $server = \App\Models\Server::findOrFail($server);
        $server->update([
            'last_heartbeat_at' => now(),
            'cpu_used' => $request->input('cpu_used'),
            'memory_used' => $request->input('memory_used'),
        ]);

        return response()->json(['success' => true]);
    });
});
