<?php

namespace App\Jobs;

use App\Events\BuildLogReceived;
use App\Events\DeploymentStageChanged;
use App\Events\DeploymentStatusChanged;
use App\Models\Deployment;
use Redis as PhpRedis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ListenDeploymentLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private ?string $applicationId = null;

    private ?string $userId = null;

    public int $timeout = 600;

    public int $tries = 5;

    public function __construct(public readonly string $deploymentId) {}

    public function handle(): void
    {
        $deployment = Deployment::with('application:id,user_id')->find($this->deploymentId);
        $this->applicationId = $deployment?->application_id;
        $this->userId = $deployment?->application?->user_id;

        $channel = 'deploy-logs:' . $this->deploymentId;
        $bufferKey = 'buffer:' . $channel;
        $terminalStatuses = ['running', 'failed', 'cancelled', 'rolled_back'];

        Log::info('ListenDeploymentLogs started', [
            'deployment_id' => $this->deploymentId,
            'channel' => $channel,
        ]);

        $redis = Redis::connection()->client();
        $originalPrefix = null;
        if ($redis instanceof PhpRedis) {
            $originalPrefix = $redis->getOption(PhpRedis::OPT_PREFIX);
            if ($originalPrefix !== '') {
                $redis->setOption(PhpRedis::OPT_PREFIX, '');
            }
        }

        try {

            // Primeiro, processar mensagens já bufferizadas (evita race condition)
            $buffered = $redis->lRange($bufferKey, 0, -1);
            Log::info('ListenDeploymentLogs buffered replay', [
                'deployment_id' => $this->deploymentId,
                'buffered_count' => is_array($buffered) ? count($buffered) : 0,
            ]);
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
            if ($redis instanceof PhpRedis) {
                $redis->setOption(PhpRedis::OPT_READ_TIMEOUT, -1);

                try {
                    $redis->subscribe([$channel], function (PhpRedis $r, string $chan, string $message) use ($terminalStatuses) {
                        $payload = json_decode($message, true);
                        if (! is_array($payload)) {
                            Log::warning('ListenDeploymentLogs invalid pubsub payload', [
                                'deployment_id' => $this->deploymentId,
                                'channel' => $chan,
                            ]);
                            return;
                        }

                        Log::info('ListenDeploymentLogs pubsub message', [
                            'deployment_id' => $this->deploymentId,
                            'channel' => $chan,
                            'type' => $payload['type'] ?? 'unknown',
                            'status' => $payload['status'] ?? null,
                        ]);

                        $shouldExit = $this->processPayload($payload, $terminalStatuses);
                        if ($shouldExit) {
                            Log::info('ListenDeploymentLogs terminal status received, unsubscribing', [
                                'deployment_id' => $this->deploymentId,
                                'status' => $payload['status'] ?? null,
                            ]);
                            $r->unsubscribe();
                        }
                    });
                } catch (\Throwable) {
                    // Normal when connection closes or worker is stopping.
                }
            } elseif (method_exists($redis, 'pubSubLoop')) {
                $pubsub = $redis->pubSubLoop();
                $pubsub->subscribe($channel);

                foreach ($pubsub as $message) {
                    if (($message->kind ?? null) !== 'message') {
                        continue;
                    }

                    $payload = json_decode($message->payload ?? '', true);
                    if (! is_array($payload)) {
                        continue;
                    }

                    $shouldExit = $this->processPayload($payload, $terminalStatuses);
                    if ($shouldExit) {
                        break;
                    }
                }

                unset($pubsub);
            }

            // Limpar buffer após conclusão
            $redis->del($bufferKey);
            Log::info('ListenDeploymentLogs finished', [
                'deployment_id' => $this->deploymentId,
            ]);
        } finally {
            if ($redis instanceof PhpRedis && $originalPrefix !== null) {
                $redis->setOption(PhpRedis::OPT_PREFIX, (string) $originalPrefix);
            }
        }
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
                $this->applicationId,
                $this->userId,
            )),

            'stage' => broadcast(new DeploymentStageChanged(
                $this->deploymentId,
                $payload['stage'] ?? '',
                $payload['status'] ?? '',
                $ts,
                $this->applicationId,
                $this->userId,
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
