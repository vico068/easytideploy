<?php

namespace App\Jobs;

use App\Events\BuildLogReceived;
use App\Events\DeploymentStageChanged;
use App\Events\DeploymentStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ListenDeploymentLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly string $deploymentId) {}

    public function handle(): void
    {
        $channel = 'deploy-logs:' . $this->deploymentId;
        $bufferKey = 'buffer:' . $channel;
        $terminalStatuses = ['running', 'failed', 'cancelled', 'rolled_back'];

        $redis = Redis::connection()->client();

        // Primeiro, processar mensagens já bufferizadas (evita race condition)
        $buffered = $redis->lRange($bufferKey, 0, -1);
        $shouldExit = false;

        foreach ($buffered as $raw) {
            $payload = json_decode($raw, true);
            if (! is_array($payload)) {
                continue;
            }

            $shouldExit = $this->processPayload($payload, $terminalStatuses);
            if ($shouldExit) {
                break;
            }
        }

        // Se já encontrou status terminal no buffer, não precisa se inscrever
        if ($shouldExit) {
            $redis->del($bufferKey);
            return;
        }

        // Agora escutar novas mensagens via Pub/Sub
        $pubsub = $redis->pubSubLoop();
        $pubsub->subscribe($channel);

        foreach ($pubsub as $message) {
            if ($message->kind !== 'message') {
                continue;
            }

            $payload = json_decode($message->payload, true);
            if (! is_array($payload)) {
                continue;
            }

            $shouldExit = $this->processPayload($payload, $terminalStatuses);
            if ($shouldExit) {
                break;
            }
        }

        unset($pubsub);

        // Limpar buffer após conclusão
        $redis->del($bufferKey);
    }

    private function processPayload(array $payload, array $terminalStatuses): bool
    {
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

        return $type === 'status' && in_array($payload['status'] ?? '', $terminalStatuses);
    }
}
