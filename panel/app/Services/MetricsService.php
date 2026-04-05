<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Container;
use App\Models\HttpMetric;
use App\Models\ResourceUsage;

class MetricsService
{
    public function __construct(
        private OrchestratorClient $orchestrator
    ) {}

    public function collectMetrics(Application $application): void
    {
        foreach ($application->runningContainers as $container) {
            try {
                $stats = $this->orchestrator->getContainerStats($container->id);

                // Update container metrics
                $container->update([
                    'cpu_usage' => $stats['cpu_percent'] ?? 0,
                    'memory_usage' => $stats['memory_percent'] ?? 0,
                ]);

                // Store historical data
                ResourceUsage::create([
                    'application_id' => $application->id,
                    'container_id' => $container->id,
                    'cpu_usage' => $stats['cpu_percent'] ?? 0,
                    'memory_usage' => $stats['memory_percent'] ?? 0,
                    'network_rx' => $stats['network_rx'] ?? 0,
                    'network_tx' => $stats['network_tx'] ?? 0,
                    'disk_read' => $stats['disk_read'] ?? 0,
                    'disk_write' => $stats['disk_write'] ?? 0,
                    'recorded_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Log error but continue with other containers
                logger()->warning("Failed to collect metrics for container {$container->id}: {$e->getMessage()}");
            }
        }
    }

    public function getAggregatedMetrics(Application $application, string $period = '1h'): array
    {
        $metrics = $application->resourceUsages()
            ->forPeriod($period)
            ->selectRaw('
                AVG(cpu_usage) as avg_cpu,
                MAX(cpu_usage) as max_cpu,
                AVG(memory_usage) as avg_memory,
                MAX(memory_usage) as max_memory,
                SUM(network_rx) as total_network_rx,
                SUM(network_tx) as total_network_tx
            ')
            ->first();

        return [
            'cpu' => [
                'average' => round($metrics->avg_cpu ?? 0, 2),
                'max' => round($metrics->max_cpu ?? 0, 2),
            ],
            'memory' => [
                'average' => round($metrics->avg_memory ?? 0, 2),
                'max' => round($metrics->max_memory ?? 0, 2),
            ],
            'network' => [
                'rx' => $metrics->total_network_rx ?? 0,
                'tx' => $metrics->total_network_tx ?? 0,
            ],
        ];
    }

    public function getHttpMetrics(Application $application, string $period = '1h'): array
    {
        $metrics = HttpMetric::where('application_id', $application->id)
            ->forPeriod($period)
            ->selectRaw('
                SUM(requests_2xx) as total_2xx,
                SUM(requests_3xx) as total_3xx,
                SUM(requests_4xx) as total_4xx,
                SUM(requests_5xx) as total_5xx,
                SUM(total_requests) as total_requests,
                AVG(avg_latency_ms) as avg_latency
            ')
            ->first();

        return [
            '2xx' => $metrics->total_2xx ?? 0,
            '3xx' => $metrics->total_3xx ?? 0,
            '4xx' => $metrics->total_4xx ?? 0,
            '5xx' => $metrics->total_5xx ?? 0,
            'total' => $metrics->total_requests ?? 0,
            'avg_latency_ms' => round($metrics->avg_latency ?? 0, 2),
        ];
    }
}
