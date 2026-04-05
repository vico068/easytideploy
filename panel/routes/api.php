<?php

use App\Http\Controllers\WebhookController;
use App\Models\HttpMetric;
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
    $commitSha = $request->input('commit_sha');
    $commitMessage = $request->input('commit_message');

    // Update build logs and commit info
    $updateData = [];
    if ($buildLogs) {
        $updateData['build_logs'] = $buildLogs;
    }
    if ($commitSha) {
        $updateData['commit_sha'] = $commitSha;
    }
    if ($commitMessage) {
        $updateData['commit_message'] = $commitMessage;
    }
    if (! empty($updateData)) {
        $deployment->update($updateData);
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

// HTTP metrics batch callback - receives Traefik metrics from orchestrator
Route::post('/internal/metrics/batch', function (\Illuminate\Http\Request $request) {
    $apiKey = $request->bearerToken();
    if (! $apiKey || ! hash_equals(config('easydeploy.orchestrator_api_key') ?? '', $apiKey)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $validated = $request->validate([
        'timestamp' => 'required|date',
        'http_metrics' => 'required|array',
        'http_metrics.*.application_id' => 'required|uuid|exists:applications,id',
        'http_metrics.*.requests_2xx' => 'integer|min:0',
        'http_metrics.*.requests_3xx' => 'integer|min:0',
        'http_metrics.*.requests_4xx' => 'integer|min:0',
        'http_metrics.*.requests_5xx' => 'integer|min:0',
        'http_metrics.*.total_requests' => 'integer|min:0',
    ]);

    // Converter timestamp para UTC (orchestrator pode enviar em timezone local)
    $recordedAt = \Carbon\Carbon::parse($validated['timestamp'])->utc()->toDateTimeString();

    foreach ($validated['http_metrics'] as $metric) {
        HttpMetric::create([
            'application_id' => $metric['application_id'],
            'requests_2xx' => $metric['requests_2xx'] ?? 0,
            'requests_3xx' => $metric['requests_3xx'] ?? 0,
            'requests_4xx' => $metric['requests_4xx'] ?? 0,
            'requests_5xx' => $metric['requests_5xx'] ?? 0,
            'total_requests' => $metric['total_requests'] ?? 0,
            'avg_latency_ms' => 0,
            'recorded_at' => $recordedAt,
        ]);
    }

    return response()->json(['success' => true, 'count' => count($validated['http_metrics'])]);
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
