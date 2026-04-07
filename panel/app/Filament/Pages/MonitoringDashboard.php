<?php

namespace App\Filament\Pages;

use App\Models\Application;
use App\Models\Container;
use App\Models\HttpMetric;
use App\Models\ResourceUsage;
use App\Services\OrchestratorClient;
use Filament\Notifications\Notification;
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

    /** @var array<string, string> Previous container statuses for change detection */
    public array $previousContainerStatuses = [];

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

        // Initialize previous container statuses for change detection
        if ($this->selectedAppId) {
            $this->previousContainerStatuses = Container::where('application_id', $this->selectedAppId)
                ->pluck('status', 'id')
                ->toArray();
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
        // PostgreSQL session timezone is America/Sao_Paulo, so all timestamps
        // stored via NOW() are in that timezone. Convert $since to match.
        $dbTz = 'America/Sao_Paulo';

        $since = match ($this->period) {
            '1h' => now($dbTz)->subHour(),
            '6h' => now($dbTz)->subHours(6),
            '24h' => now($dbTz)->subDay(),
            '7d' => now($dbTz)->subWeek(),
            default => now($dbTz)->subHour(),
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
        $networkChartData = ['labels' => [], 'datasets' => []];
        $httpChartData = ['labels' => [], '2xx' => [], '3xx' => [], '4xx' => [], '5xx' => []];

        if ($selectedApp) {
            $containers = Container::where('application_id', $selectedApp->id)->get();
            $runningContainers = $containers->where('status', 'running')->count();

            // CPU/RAM from resource_usages (last 5 minutes average)
            $containerIds = $containers->where('status', 'running')->pluck('id')->toArray();
            $recentStats = null;
            if (! empty($containerIds)) {
                $recentStats = ResourceUsage::whereIn('container_id', $containerIds)
                    ->where('recorded_at', '>=', now($dbTz)->subMinutes(5))
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

            // Gráfico de rede por container (RX/TX em MB)
            if (! empty($containerIds)) {
                $netQuery = ResourceUsage::whereIn('container_id', $containerIds)
                    ->where('recorded_at', '>=', $since);

                if ($this->selectedContainerId) {
                    $netQuery->where('container_id', $this->selectedContainerId);
                }

                $netData = $netQuery
                    ->select([
                        'container_id',
                        DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                        DB::raw('MAX(network_rx) as max_rx'),
                        DB::raw('MAX(network_tx) as max_tx'),
                    ])
                    ->groupBy('container_id', 'period')
                    ->orderBy('period')
                    ->get();

                $netLabels = $netData->pluck('period')
                    ->unique()
                    ->map(fn ($d) => \Carbon\Carbon::parse($d)->format($dateFormat))
                    ->values()
                    ->toArray();

                $netDatasets = [];
                $netColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                $netColorIdx = 0;

                foreach ($netData->groupBy('container_id') as $containerId => $data) {
                    $containerName = $containers->firstWhere('id', $containerId)?->name ?? substr($containerId, 0, 8);
                    $color = $netColors[$netColorIdx % count($netColors)];
                    $netColorIdx++;

                    // Convert cumulative bytes to MB
                    $rxValues = $data->pluck('max_rx')->values()->toArray();
                    $txValues = $data->pluck('max_tx')->values()->toArray();

                    // Convert to delta (difference between consecutive points) in MB
                    $rxDelta = [];
                    $txDelta = [];
                    for ($i = 0; $i < count($rxValues); $i++) {
                        if ($i === 0) {
                            $rxDelta[] = 0;
                            $txDelta[] = 0;
                        } else {
                            $rxDelta[] = round(max(0, ((int) $rxValues[$i] - (int) $rxValues[$i - 1]) / (1024 * 1024)), 2);
                            $txDelta[] = round(max(0, ((int) $txValues[$i] - (int) $txValues[$i - 1]) / (1024 * 1024)), 2);
                        }
                    }

                    $netDatasets[] = [
                        'label' => $containerName.' RX (MB)',
                        'data' => $rxDelta,
                        'borderColor' => $color,
                        'backgroundColor' => $color.'1a',
                        'fill' => true,
                        'tension' => 0.4,
                        'borderWidth' => 2,
                        'pointRadius' => 0,
                    ];

                    $netDatasets[] = [
                        'label' => $containerName.' TX (MB)',
                        'data' => $txDelta,
                        'borderColor' => $color,
                        'backgroundColor' => $color.'0d',
                        'borderDash' => [5, 5],
                        'fill' => true,
                        'tension' => 0.4,
                        'borderWidth' => 2,
                        'pointRadius' => 0,
                    ];
                }

                $networkChartData = ['labels' => $netLabels, 'datasets' => $netDatasets];
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
            'networkChartData' => $networkChartData,
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

    public function onContainerStatusChanged(array $event): void
    {
        $containerId = $event['container_id'] ?? '';
        $name = $event['name'] ?? 'desconhecido';
        $status = $event['status'] ?? '';
        $previous = $this->previousContainerStatuses[$containerId] ?? null;

        if ($previous === 'running' && $status !== 'running') {
            Notification::make()
                ->warning()
                ->title('Container parou')
                ->body("Container {$name} mudou para {$status}")
                ->send();
        }

        $this->previousContainerStatuses[$containerId] = $status;
    }

    protected function getListeners(): array
    {
        $applicationId = (string) ($this->selectedAppId ?? '');
        if ($applicationId === '') {
            return [];
        }

        return [
            "echo-private:application.{$applicationId},ContainerStatusChanged" => 'onContainerStatusChanged',
        ];
    }
}

