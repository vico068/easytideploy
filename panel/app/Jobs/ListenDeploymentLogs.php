<?php

namespace App\Jobs;

use App\Events\BuildLogReceived;
use App\Events\DeploymentStageChanged;
use App\Events\DeploymentStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class ListenDeploymentLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly string $deploymentId) {}

    public function handle(): void
    {
        $redis = Redis::connection()->client();
        $pubsub = $redis->pubSubLoop();
        $pubsub->subscribe('deploy-logs:' . $this->deploymentId);

        $terminalStatuses = ['running', 'failed', 'cancelled', 'rolled_back'];

        foreach ($pubsub as $message) {
            if ($message->kind !== 'message') {
                continue;
            }

            $payload = json_decode($message->payload, true);
            if (! is_array($payload)) {
                continue;
            }

            $type = $payload['type'] ?? 'log';
            $ts = $payload['ts'] ?? now()->toIso8601String();

            match ($type) {
                'log' => broadcast(new BuildLogReceived(
                    $this->deploymentId,
                    $payload['line'] ?? '',
                    $payload['stage'] ?? 'build',
                    $ts,
                )),

                'stage' => broadcast(new DeploymentStageChanged(
                    $this->deploymentId,
                    $payload['stage'] ?? '',
                    $payload['status'] ?? '',
                    $ts,
                )),

                'status' => broadcast(new DeploymentStatusChanged(
                    $this->deploymentId,
                    $payload['status'] ?? '',
                    $payload['error'] ?? null,
                )),

                default => null,
            };

            if ($type === 'status' && in_array($payload['status'] ?? '', $terminalStatuses)) {
                break;
            }
        }

        unset($pubsub);
    }
}
