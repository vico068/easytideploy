<?php

namespace App\Filament\Pages;

use App\Models\Container;
use App\Models\Deployment;
use App\Models\Server;
use Filament\Pages\Page;

class MonitoringDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Monitoramento';

    protected static ?string $navigationGroup = 'Infraestrutura';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Painel de Monitoramento';

    protected static string $view = 'filament.pages.monitoring-dashboard';

    public string $period = '1h';

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->dispatch('refresh-metrics');
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

        $servers = Server::with(['containers'])->get();
        $runningContainers = Container::where('status', 'running')->count();
        $unhealthyContainers = Container::where('health_status', 'unhealthy')->count();
        $recentDeployments = Deployment::with('application')
            ->latest()
            ->limit(5)
            ->get();

        $failedDeployments = Deployment::where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->count();

        return [
            'servers' => $servers,
            'runningContainers' => $runningContainers,
            'unhealthyContainers' => $unhealthyContainers,
            'recentDeployments' => $recentDeployments,
            'failedDeployments' => $failedDeployments,
            'period' => $this->period,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\MonitoringStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\ResourceUsageChart::class,
            \App\Filament\Widgets\RequestsChart::class,
            \App\Filament\Widgets\StatusCodesChart::class,
            \App\Filament\Widgets\ServerMetricsChart::class,
            \App\Filament\Widgets\RecentDeploymentsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|string|array
    {
        return 2;
    }
}
