<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeploymentLogsController extends Controller
{
    /**
     * Stream deployment logs via Server-Sent Events (SSE).
     *
     * This endpoint provides real-time log streaming by reading directly from
     * Redis Pub/Sub where the orchestrator publishes logs. No WebSocket needed.
     */
    public function stream(Request $request, Deployment $deployment): StreamedResponse
    {
        // Check authorization via Filament's auth guard
        $user = $request->user() ?? auth()->user();
        if (! $user || $deployment->application->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        return new StreamedResponse(function () use ($deployment) {
            // Disable output buffering for real-time streaming
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Set unlimited execution time for this request
            set_time_limit(0);

            // Ignore user abort to clean up properly
            ignore_user_abort(false);

            $deploymentId = $deployment->id;
            $channel = 'deploy-logs:' . $deploymentId;
            $bufferKey = 'buffer:' . $channel;
            $terminalStatuses = ['running', 'failed', 'cancelled', 'rolled_back'];

            // Send initial status
            $this->sendEvent('status', [
                'status' => $deployment->status->value,
                'isTerminal' => $deployment->status->isTerminal(),
            ]);

            // If deployment is already terminal, send existing logs and exit
            if ($deployment->status->isTerminal()) {
                // Send existing logs from database
                if ($deployment->build_logs) {
                    $lines = array_filter(explode("\n", $deployment->build_logs));
                    foreach ($lines as $line) {
                        $this->sendEvent('log', [
                            'line' => trim($line),
                            'stage' => 'build',
                            'ts' => now()->toIso8601String(),
                        ]);
                    }
                }
                $this->sendEvent('done', ['reason' => 'terminal_status']);
                return;
            }

            $redis = Redis::connection()->client();

            // Track which messages we've sent to avoid duplicates
            $processedCount = 0;

            // First, send buffered messages (avoids race condition)
            $buffered = $redis->lRange($bufferKey, 0, -1);
            $shouldExit = false;

            foreach ($buffered as $raw) {
                if (connection_aborted()) {
                    return;
                }

                $payload = json_decode($raw, true);
                if (! is_array($payload)) {
                    continue;
                }

                $shouldExit = $this->processAndSendPayload($payload, $terminalStatuses);
                $processedCount++;

                if ($shouldExit) {
                    break;
                }
            }

            // If terminal status found in buffer, exit
            if ($shouldExit) {
                $this->sendEvent('done', ['reason' => 'terminal_status']);
                return;
            }

            // Send a heartbeat to confirm connection is working
            $this->sendEvent('heartbeat', ['ts' => now()->toIso8601String()]);

            // Now listen for new messages via Pub/Sub
            $pubsub = $redis->pubSubLoop();
            $pubsub->subscribe($channel);

            // Set a timeout for the subscription (10 minutes max)
            $startTime = time();
            $maxDuration = 600; // 10 minutes
            $lastHeartbeat = time();

            foreach ($pubsub as $message) {
                // Check for connection abort
                if (connection_aborted()) {
                    break;
                }

                // Check timeout
                $elapsed = time() - $startTime;
                if ($elapsed > $maxDuration) {
                    $this->sendEvent('timeout', ['reason' => 'max_duration_exceeded']);
                    break;
                }

                // Send periodic heartbeat every 30 seconds
                if ((time() - $lastHeartbeat) >= 30) {
                    $this->sendEvent('heartbeat', ['ts' => now()->toIso8601String()]);
                    $lastHeartbeat = time();
                }

                if ($message->kind !== 'message') {
                    continue;
                }

                $payload = json_decode($message->payload, true);
                if (! is_array($payload)) {
                    continue;
                }

                $shouldExit = $this->processAndSendPayload($payload, $terminalStatuses);
                if ($shouldExit) {
                    break;
                }
            }

            unset($pubsub);
            $this->sendEvent('done', ['reason' => 'stream_ended']);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Process payload and send appropriate SSE event.
     */
    private function processAndSendPayload(array $payload, array $terminalStatuses): bool
    {
        $type = $payload['type'] ?? 'log';

        match ($type) {
            'log' => $this->sendEvent('log', [
                'line' => $payload['line'] ?? '',
                'stage' => $payload['stage'] ?? 'build',
                'ts' => $payload['ts'] ?? now()->toIso8601String(),
            ]),

            'stage' => $this->sendEvent('stage', [
                'stage' => $payload['stage'] ?? '',
                'status' => $payload['status'] ?? '',
                'ts' => $payload['ts'] ?? now()->toIso8601String(),
            ]),

            'status' => $this->sendEvent('status', [
                'status' => $payload['status'] ?? '',
                'error' => $payload['error'] ?? null,
            ]),

            default => null,
        };

        return $type === 'status' && in_array($payload['status'] ?? '', $terminalStatuses);
    }

    /**
     * Send a Server-Sent Event.
     */
    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";

        // Flush output
        flush();
    }
}
