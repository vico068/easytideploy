<?php

namespace App\Filament\Pages;

use App\Models\Container;
use App\Models\Deployment;
use App\Models\HttpMetric;
use App\Models\ResourceUsage;
use App\Models\Server;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class MonitoringDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Monitoramento';

    protected static ?string $navigationGroup = 'Infraestrutura';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Monitoramento';

    protected static string $view = 'filament.pages.monitoring-dashboard';

    public string $period = '1h';

    public function setPeriod(string $period): void
    {
        $this->period = $period;
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

        $dateFormat = in_array($this->period, ['7d', '30d']) ? 'd/m H:i' : 'H:i';

        // Servers and containers
        $servers = Server::with(['containers'])->get();
        $runningContainers = Container::where('status', 'running')->count();
        $unhealthyContainers = Container::where('health_status', 'unhealthy')->count();

        // Summary stats
        $onlineServers = Server::where('status', 'online')->count();
        $totalServers = Server::count();
        $avgCpu = Server::where('status', 'online')->avg('cpu_used') ?? 0;
        $avgMemory = Server::where('status', 'online')->avg('memory_used') ?? 0;
        $totalRequests = HttpMetric::where('recorded_at', '>=', $since)->sum('total_requests');

        // Resource usage chart
        $resourceData = ResourceUsage::whereNotNull('server_id')
            ->where('recorded_at', '>=', $since)
            ->select([
                DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                DB::raw('AVG(cpu_percent) as avg_cpu'),
                DB::raw('AVG(memory_percent) as avg_memory'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // HTTP requests chart
        $httpData = HttpMetric::where('recorded_at', '>=', $since)
            ->select([
                DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                DB::raw('SUM(total_requests) as total'),
                DB::raw('SUM(requests_2xx) as success'),
                DB::raw('SUM(requests_4xx + requests_5xx) as errors'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Recent deployments
        $recentDeployments = Deployment::with('application')
            ->latest()
            ->limit(8)
            ->get();

        return [
            'period' => $this->period,
            'servers' => $servers,
            'onlineServers' => $onlineServers,
            'totalServers' => $servers->count(),
            'runningContainers' => $runningContainers,
            'unhealthyContainers' => $unhealthyContainers,
            'avgCpu' => round($avgCpu, 1),
            'avgMemory' => round($avgMemory, 1),
            'totalRequests' => $totalRequests,
            'recentDeployments' => $recentDeployments,
            'resourceChartData' => [
                'labels' => $resourceData->pluck('period')->map(fn ($d) => \Carbon\Carbon::parse($d)->format($dateFormat))->toArray(),
                'cpu' => $resourceData->pluck('avg_cpu')->map(fn ($v) => round((float) $v, 2))->toArray(),
                'memory' => $resourceData->pluck('avg_memory')->map(fn ($v) => round((float) $v, 2))->toArray(),
            ],
            'httpChartData' => [
                'labels' => $httpData->pluck('period')->map(fn ($d) => \Carbon\Carbon::parse($d)->format($dateFormat))->toArray(),
                'total' => $httpData->pluck('total')->toArray(),
                'success' => $httpData->pluck('success')->toArray(),
                'errors' => $httpData->pluck('errors')->toArray(),
            ],
        ];
    }
}
