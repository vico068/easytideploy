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

    public function deploy(Application $app, ?string $commitSha = null): array
    {
        return $this->http->post('/api/v1/deployments', [
            'application_id' => $app->id,
            'git_repository' => $app->git_repository,
            'git_branch' => $app->git_branch,
            'commit_sha' => $commitSha,
            'type' => $app->type->value,
            'build_command' => $app->build_command,
            'start_command' => $app->start_command,
            'port' => $app->port,
            'replicas' => $app->replicas,
            'cpu_limit' => $app->cpu_limit,
            'memory_limit' => $app->memory_limit,
            'environment' => $app->getEnvironmentArray(),
            'health_check' => $app->health_check,
        ])->throw()->json();
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

    public function rollback(Application $app, string $deploymentId): array
    {
        return $this->http->post("/api/v1/applications/{$app->id}/rollback", [
            'deployment_id' => $deploymentId,
        ])->throw()->json();
    }

    public function updateProxyConfig(Application $app): array
    {
        return $this->http->post('/api/v1/proxy/sync', [
            'application_id' => $app->id,
            'domains' => $app->domains->pluck('domain')->toArray(),
            'containers' => $app->containers()
                ->where('status', 'running')
                ->get()
                ->map(fn ($c) => [
                    'ip' => $c->internal_ip,
                    'port' => $c->internal_port,
                    'server' => $c->server->hostname,
                ])
                ->toArray(),
        ])->throw()->json();
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
}
