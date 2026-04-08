<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OrchestratorClient
{
    private PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl(config('easydeploy.orchestrator_url'))
            ->withToken(config('easydeploy.orchestrator_api_key'))
            ->asJson()
            ->accept('application/json')
            ->timeout(30)
            ->retry(3, 100);
    }

    public function deploy(Application $app, ?string $deploymentId = null, ?string $commitSha = null, ?string $callbackUrl = null): array
    {
        $environment = $app->getEnvironmentArray();

        $payload = [
            'application_id' => $app->id,
            'git_repository' => $app->git_repository,
            'git_branch' => $app->git_branch,
            'commit_sha' => $commitSha ?? '',
            'git_token' => $app->git_token ?? '',
            'type' => $app->type->value,
            'build_command' => $app->build_command,
            'start_command' => $app->start_command,
            'root_directory' => $app->root_directory ?? '/',
            'port' => $app->port,
            'replicas' => $app->replicas,
            'cpu_limit' => $app->cpu_limit,
            'memory_limit' => $app->memory_limit,
            'environment' => empty($environment) ? new \stdClass() : $environment,
            'health_check' => $app->health_check,
            'callback_url' => $callbackUrl ?? '',
        ];

        // Add deployment_id if provided (panel-initiated deploys)
        if ($deploymentId) {
            $payload['deployment_id'] = $deploymentId;
        }

        return $this->http->post('/api/v1/deployments', $payload)->throw()->json();
    }

    public function scale(Application $app, int $replicas): array
    {
        return $this->http->post("/api/v1/applications/{$app->id}/scale", [
            'replicas' => $replicas,
        ])->throw()->json();
    }

    public function stop(Application $app): array
    {
        return $this->http->post("/api/v1/applications/{$app->id}/stop")
            ->throw()->json();
    }

    public function restart(Application $app): array
    {
        return $this->http->post("/api/v1/applications/{$app->id}/restart")
            ->throw()->json();
    }

    public function getLogs(Application $app, int $lines = 100, ?string $containerId = null): array
    {
        return $this->http->get("/api/v1/applications/{$app->id}/logs", [
            'lines' => $lines,
            'container_id' => $containerId,
        ])->throw()->json();
    }

    public function getMetrics(Application $app): array
    {
        return $this->http->get("/api/v1/applications/{$app->id}/metrics")
            ->throw()->json();
    }

    public function getServers(): array
    {
        return $this->http->get('/api/v1/servers')->throw()->json();
    }

    public function rollback(
        Application $app,
        string $targetDeploymentId,
        ?string $rollbackDeploymentId = null,
        ?string $callbackUrl = null
    ): array
    {
        return $this->http->post("/api/v1/applications/{$app->id}/rollback", [
            'target_deployment_id' => $targetDeploymentId,
            'rollback_deployment_id' => $rollbackDeploymentId,
            'git_token' => $app->git_token ?? '',
            'callback_url' => $callbackUrl ?? '',
        ])->throw()->json();
    }

    public function updateProxyConfig(Application $app): array
    {
        return $this->http->post("/api/v1/proxy/sync/{$app->id}")
            ->throw()->json();
    }

    public function cancelDeployment(string $deploymentId): array
    {
        return $this->http->post("/api/v1/deployments/{$deploymentId}/cancel")
            ->throw()->json();
    }

    public function getContainerStats(string $containerId): array
    {
        return $this->http->get("/api/v1/containers/{$containerId}/stats")
            ->throw()->json();
    }

    public function stopContainer(string $containerId): array
    {
        return $this->http->post("/api/v1/containers/{$containerId}/stop")
            ->throw()->json();
    }

    public function restartContainer(string $containerId): array
    {
        return $this->http->post("/api/v1/containers/{$containerId}/restart")
            ->throw()->json();
    }

    public function deleteTraefikConfig(Application $app): array
    {
        return $this->http->delete("/api/v1/proxy/config/{$app->id}")
            ->throw()->json();
    }
}
