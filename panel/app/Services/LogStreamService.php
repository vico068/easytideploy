<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Container;
use Illuminate\Support\Collection;

class LogStreamService
{
    public function __construct(
        private OrchestratorClient $orchestrator
    ) {}

    public function getApplicationLogs(
        Application $application,
        int $lines = 100,
        ?string $containerId = null,
        ?string $level = null,
        ?string $search = null
    ): Collection {
        $query = $application->logs()->latest('timestamp');

        if ($containerId) {
            $query->where('container_id', $containerId);
        }

        if ($level) {
            $query->where('level', $level);
        }

        if ($search) {
            $query->where('message', 'like', "%{$search}%");
        }

        return $query->limit($lines)->get()->reverse()->values();
    }

    public function getContainerLogs(Container $container, int $lines = 100, ?string $level = null): Collection
    {
        $query = $container->logs()->latest('timestamp');

        if ($level) {
            $query->where('level', $level);
        }

        return $query->limit($lines)->get()->reverse()->values();
    }

    public function getLogsSince(Application $application, string $since, ?string $containerId = null): Collection
    {
        $query = $application->logs()
            ->where('timestamp', '>', $since)
            ->orderBy('timestamp', 'asc');

        if ($containerId) {
            $query->where('container_id', $containerId);
        }

        return $query->limit(100)->get();
    }

    public function fetchRemoteLogs(Application $application, int $lines = 100, ?string $containerId = null): array
    {
        try {
            return $this->orchestrator->getLogs($application, $lines, $containerId);
        } catch (\Exception $e) {
            logger()->warning("Failed to fetch remote logs: {$e->getMessage()}");
            return [];
        }
    }

    public function downloadLogs(Application $application, ?string $containerId = null, ?string $level = null, ?string $since = null): string
    {
        $query = $application->logs()->orderBy('timestamp', 'asc');

        if ($containerId) {
            $query->where('container_id', $containerId);
        }

        if ($level) {
            $query->where('level', $level);
        }

        if ($since) {
            $query->where('timestamp', '>=', $since);
        }

        $logs = $query->get();

        $output = '';
        foreach ($logs as $log) {
            $output .= sprintf(
                "[%s] [%s] %s%s\n",
                $log->timestamp->format('Y-m-d H:i:s.v'),
                strtoupper($log->level->value),
                $log->container_id ? "[{$log->container_id}] " : '',
                $log->message
            );
        }

        return $output;
    }

    public function getLogStats(Application $application, string $period = '1h'): array
    {
        $since = match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            default => now()->subHour(),
        };

        $total = $application->logs()->where('timestamp', '>=', $since)->count();
        $errors = $application->logs()->where('timestamp', '>=', $since)->where('level', 'error')->count();
        $warnings = $application->logs()->where('timestamp', '>=', $since)->where('level', 'warning')->count();
        $criticals = $application->logs()->where('timestamp', '>=', $since)->where('level', 'critical')->count();

        return [
            'total' => $total,
            'errors' => $errors,
            'warnings' => $warnings,
            'criticals' => $criticals,
            'info' => $total - $errors - $warnings - $criticals,
        ];
    }
}
