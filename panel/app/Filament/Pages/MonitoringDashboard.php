<?php

namespace App\Filament\Pages;

use App\Models\Application;
use App\Models\Container;
use App\Models\HttpMetric;
use App\Models\ResourceUsage;
use App\Services\OrchestratorClient;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitoringDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Monitoramento';

    protected static ?string $navigationGroup = 'Aplicações';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Monitoramento';

    protected static string $view = 'filament.pages.monitoring-dashboard';

    public string $period = '1h';

    public ?string $selectedAppId = null;

    public ?string $selectedContainerId = null;

    public function mount(): void
    {
        $appId = request()->query('app');

        if ($appId) {
            $app = Application::where('user_id', auth()->id())->find($appId);
            $this->selectedAppId = $app?->id;
        }

        if (! $this->selectedAppId) {
            $this->selectedAppId = Application::where('user_id', auth()->id())
                ->value('id');
        }
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->dispatch('charts-need-update');
    }

    public function setSelectedApp(string $appId): void
    {
        $app = Application::where('user_id', auth()->id())->find($appId);
        $this->selectedAppId = $app?->id;
        $this->selectedContainerId = null;
        $this->dispatch('charts-need-update');
    }

    public function setSelectedContainer(?string $containerId): void
    {
        $this->selectedContainerId = $containerId;
        $this->dispatch('charts-need-update');
    }

    protected function getViewData(): array
    {
        $since = match ($this->period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            default => now()->subHour(),
        };

        $bucketSeconds = match ($this->period) {
            '1h' => 300,
            '6h' => 1800,
            '24h' => 3600,
            '7d' => 21600,
            default => 300,
        };

        $dateFormat = in_array($this->period, ['7d']) ? 'd/m H:i' : 'H:i';

        $userId = auth()->id();

        $applications = Application::where('user_id', $userId)
            ->withCount(['containers' => fn ($q) => $q->where('status', 'running')])
            ->get();

        $selectedApp = $this->selectedAppId
            ? $applications->firstWhere('id', $this->selectedAppId)
            : $applications->first();

        $containers = collect();
        $runningContainers = 0;
        $avgCpu = 0;
        $avgMemory = 0;
        $totalRequests = 0;
        $resourceChartData = ['labels' => [], 'datasets' => []];
        $httpChartData = ['labels' => [], '2xx' => [], '3xx' => [], '4xx' => [], '5xx' => []];

        if ($selectedApp) {
            $containers = Container::where('application_id', $selectedApp->id)->get();
            $runningContainers = $containers->where('status', 'running')->count();

            // CPU/RAM from resource_usages (last 5 minutes average)
            $containerIds = $containers->where('status', 'running')->pluck('id')->toArray();
            $recentStats = null;
            if (! empty($containerIds)) {
                $recentStats = ResourceUsage::whereIn('container_id', $containerIds)
                    ->where('recorded_at', '>=', now()->subMinutes(5))
                    ->select([
                        DB::raw('AVG(cpu_percent) as avg_cpu'),
                        DB::raw('AVG(memory_percent) as avg_memory'),
                    ])
                    ->first();
            }
            $avgCpu = round((float) ($recentStats?->avg_cpu ?? 0), 1);
            $avgMemory = round((float) ($recentStats?->avg_memory ?? 0), 1);

            // HTTP metrics para a app
            $totalRequests = HttpMetric::where('application_id', $selectedApp->id)
                ->where('recorded_at', '>=', $since)
                ->sum('total_requests');

            // Gráfico HTTP por código
            $httpData = HttpMetric::where('application_id', $selectedApp->id)
                ->where('recorded_at', '>=', $since)
                ->select([
                    DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                    DB::raw('SUM(requests_2xx) as r2xx'),
                    DB::raw('SUM(requests_3xx) as r3xx'),
                    DB::raw('SUM(requests_4xx) as r4xx'),
                    DB::raw('SUM(requests_5xx) as r5xx'),
                ])
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            $httpChartData = [
                'labels' => $httpData->pluck('period')->map(fn ($d) => \Carbon\Carbon::parse($d)->format($dateFormat))->toArray(),
                '2xx' => $httpData->pluck('r2xx')->toArray(),
                '3xx' => $httpData->pluck('r3xx')->toArray(),
                '4xx' => $httpData->pluck('r4xx')->toArray(),
                '5xx' => $httpData->pluck('r5xx')->toArray(),
            ];

            // Gráfico de recursos por container
            $containerIds = $containers->pluck('id')->toArray();

            if (! empty($containerIds)) {
                $containerQuery = ResourceUsage::whereIn('container_id', $containerIds)
                    ->where('recorded_at', '>=', $since);

                if ($this->selectedContainerId) {
                    $containerQuery->where('container_id', $this->selectedContainerId);
                }

                $resourceData = $containerQuery
                    ->select([
                        'container_id',
                        DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                        DB::raw('AVG(cpu_percent) as avg_cpu'),
                        DB::raw('AVG(memory_percent) as avg_memory'),
                    ])
                    ->groupBy('container_id', 'period')
                    ->orderBy('period')
                    ->get();

                $labels = $resourceData->pluck('period')
                    ->unique()
                    ->map(fn ($d) => \Carbon\Carbon::parse($d)->format($dateFormat))
                    ->values()
                    ->toArray();

                $datasets = [];
                $colors = ['#0d8bfa', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                $colorIdx = 0;

                foreach ($resourceData->groupBy('container_id') as $containerId => $data) {
                    $containerName = $containers->firstWhere('id', $containerId)?->name ?? substr($containerId, 0, 8);
                    $color = $colors[$colorIdx % count($colors)];
                    $colorIdx++;

                    $datasets[] = [
                        'label' => $containerName.' CPU%',
                        'data' => $data->pluck('avg_cpu')->map(fn ($v) => round((float) $v, 2))->values()->toArray(),
                        'borderColor' => $color,
                        'backgroundColor' => $color.'1a',
                        'fill' => false,
                        'tension' => 0.4,
                        'borderWidth' => 2,
                        'pointRadius' => 0,
                        'type' => 'cpu',
                    ];

                    $datasets[] = [
                        'label' => $containerName.' RAM%',
                        'data' => $data->pluck('avg_memory')->map(fn ($v) => round((float) $v, 2))->values()->toArray(),
                        'borderColor' => $color,
                        'backgroundColor' => $color.'1a',
                        'borderDash' => [5, 5],
                        'fill' => false,
                        'tension' => 0.4,
                        'borderWidth' => 2,
                        'pointRadius' => 0,
                        'type' => 'memory',
                    ];
                }

                $resourceChartData = compact('labels', 'datasets');
            }
        }

        return [
            'period' => $this->period,
            'applications' => $applications,
            'selectedApp' => $selectedApp,
            'containers' => $containers,
            'selectedContainerId' => $this->selectedContainerId,
            'runningContainers' => $runningContainers,
            'avgCpu' => $avgCpu,
            'avgMemory' => $avgMemory,
            'totalRequests' => $totalRequests,
            'resourceChartData' => $resourceChartData,
            'httpChartData' => $httpChartData,
            'logs' => $selectedApp ? $this->getContainerLogs($selectedApp->id) : collect(),
        ];
    }

    protected function getContainerLogs($appId): \Illuminate\Support\Collection
    {
        try {
            $app = Application::find($appId);
            if (! $app) {
                return collect();
            }

            $orchestrator = app(OrchestratorClient::class);
            $response = $orchestrator->getLogs($app, 100, $this->selectedContainerId);

            $logs = collect();
            $logsMap = $response['logs'] ?? [];

            foreach ($logsMap as $containerName => $logText) {
                if (! is_string($logText) || $logText === '') {
                    continue;
                }

                $lines = explode("\n", rtrim($logText, "\n"));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    // Try to parse common log formats for level/timestamp
                    $level = 'info';
                    $timestamp = null;
                    $message = $line;

                    // Match Docker timestamp prefix: 2026-04-05T10:25:03.123456789Z
                    if (preg_match('/^(\d{4}-\d{2}-\d{2}T[\d:.]+Z?)\s+(.*)$/u', $line, $m)) {
                        try {
                            $timestamp = \Carbon\Carbon::parse($m[1]);
                        } catch (\Throwable) {
                            // ignore
                        }
                        $message = $m[2];
                    }

                    // Detect level from message content
                    $lower = strtolower($message);
                    if (str_contains($lower, 'error') || str_contains($lower, 'fatal') || str_contains($lower, 'panic')) {
                        $level = 'error';
                    } elseif (str_contains($lower, 'warn')) {
                        $level = 'warning';
                    } elseif (str_contains($lower, 'debug') || str_contains($lower, 'trace')) {
                        $level = 'debug';
                    }

                    $logs->push((object) [
                        'container_name' => $containerName,
                        'level' => $level,
                        'timestamp' => $timestamp,
                        'message' => $message,
                    ]);
                }
            }

            return $logs->take(-100)->values();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch container logs from orchestrator', [
                'app_id' => $appId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    public function refreshLogs(): void
    {
        // Livewire re-renderiza automaticamente
    }
}

