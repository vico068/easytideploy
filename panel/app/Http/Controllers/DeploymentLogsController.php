<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Redis as PhpRedis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeploymentLogsController extends Controller
{
    public function show(Request $request, Deployment $deployment)
    {
        $user = $request->user() ?? auth()->user();
        if (! $user || $deployment->application->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'id' => $deployment->id,
            'status' => $deployment->status->value,
            'logs' => $deployment->build_logs ?? '',
        ]);
    }

    /**
     * Stream deployment logs via Server-Sent Events (SSE).
     *
     * Reads directly from Redis Pub/Sub (phpredis extension).
     * No WebSocket, no predis — uses php-redis subscribe() callback.
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

            // Unlimited execution time for long-running builds
            set_time_limit(0);
            ignore_user_abort(false);

            $deploymentId = $deployment->id;
            $channel      = 'deploy-logs:' . $deploymentId;
            $bufferKey    = 'buffer:' . $channel;
            $terminalStatuses = ['running', 'failed', 'cancelled', 'rolled_back'];

            // Send initial deployment status
            $this->sendEvent('status', [
                'status'     => $deployment->status->value,
                'isTerminal' => $deployment->status->isTerminal(),
            ]);

            // Already finished — replay logs from DB and close
            if ($deployment->status->isTerminal()) {
                if ($deployment->build_logs) {
                    foreach (array_filter(explode("\n", $deployment->build_logs)) as $line) {
                        $this->sendEvent('log', [
                            'line'  => trim($line),
                            'stage' => 'build',
                            'ts'    => now()->toIso8601String(),
                        ]);
                    }
                }
                $this->sendEvent('done', ['reason' => 'terminal_status']);

                return;
            }

            /** @var \Redis $redis */
            $redis = Redis::connection()->client();
            $originalPrefix = null;
            if ($redis instanceof PhpRedis) {
                $originalPrefix = $redis->getOption(PhpRedis::OPT_PREFIX);
                if ($originalPrefix !== '') {
                    $redis->setOption(PhpRedis::OPT_PREFIX, '');
                }
            }

            try {

            // ----------------------------------------------------------------
            // 1. Replay buffered messages published before we subscribed
            //    (avoids race condition where build finishes before client connects)
            // ----------------------------------------------------------------
                $buffered  = $redis->lRange($bufferKey, 0, -1);
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
                    if ($shouldExit) {
                        break;
                    }
                }

                if ($shouldExit) {
                    $this->sendEvent('done', ['reason' => 'terminal_status']);

                    return;
                }

            // Confirm we are live
                $this->sendEvent('heartbeat', ['ts' => now()->toIso8601String()]);

            // ----------------------------------------------------------------
            // 2. Live Pub/Sub  — phpredis subscribe() blocks until callback
            //    calls unsubscribe() or the connection times out.
            // ----------------------------------------------------------------
                $maxDuration = 600; // 10 minutes
                $startTime   = time();

            // Allow the blocking subscribe call to run for up to $maxDuration seconds.
            // OPT_READ_TIMEOUT = -1 means "block forever"; we control abort manually.
                $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);

                $ctrl = $this; // capture for use inside closure

                try {
                    $redis->subscribe(
                        [$channel],
                        function (\Redis $r, string $chan, string $message) use (
                            $ctrl,
                            $terminalStatuses,
                            $startTime,
                            $maxDuration
                        ) {
                            if (connection_aborted()) {
                                $r->unsubscribe();

                                return;
                            }

                            if ((time() - $startTime) > $maxDuration) {
                                $ctrl->sendEvent('timeout', ['reason' => 'max_duration_exceeded']);
                                $r->unsubscribe();

                                return;
                            }

                            $payload = json_decode($message, true);
                            if (! is_array($payload)) {
                                return;
                            }

                            $exit = $ctrl->processAndSendPayload($payload, $terminalStatuses);
                            if ($exit) {
                                $r->unsubscribe();
                            }
                        }
                    );
                } catch (\Throwable) {
                    // Connection closed by client or network error — normal exit path
                }

                $this->sendEvent('done', ['reason' => 'stream_ended']);
            } finally {
                if ($redis instanceof PhpRedis && $originalPrefix !== null) {
                    $redis->setOption(PhpRedis::OPT_PREFIX, (string) $originalPrefix);
                }
            }
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache',
            'Connection'       => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Process a single Pub/Sub payload and emit the matching SSE event.
     *
     * Returns true if the stream should terminate (terminal status received).
     *
     * @param  array<string,mixed>  $payload
     * @param  string[]             $terminalStatuses
     */
    public function processAndSendPayload(array $payload, array $terminalStatuses): bool
    {
        $type = $payload['type'] ?? 'log';

        match ($type) {
            'log' => $this->sendEvent('log', [
                'line'  => $payload['line'] ?? '',
                'stage' => $payload['stage'] ?? 'build',
                'ts'    => $payload['ts'] ?? now()->toIso8601String(),
            ]),
            'stage' => $this->sendEvent('stage', [
                'stage'  => $payload['stage'] ?? '',
                'status' => $payload['status'] ?? '',
                'ts'     => $payload['ts'] ?? now()->toIso8601String(),
            ]),
            'status' => $this->sendEvent('status', [
                'status' => $payload['status'] ?? '',
                'error'  => $payload['error'] ?? null,
            ]),
            default => null,
        };

        return $type === 'status' && in_array($payload['status'] ?? '', $terminalStatuses);
    }

    /**
     * Emit a single Server-Sent Event frame and flush.
     *
     * @param  array<string,mixed>  $data
     */
    public function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        flush();
    }
}
