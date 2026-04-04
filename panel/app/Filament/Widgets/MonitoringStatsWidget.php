<?php

namespace App\Filament\Widgets;

use App\Models\Container;
use App\Models\Deployment;
use App\Models\Server;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonitoringStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $onlineServers = Server::where('status', 'online')->count();
        $totalServers = Server::count();
        $avgCpu = Server::where('status', 'online')->avg('cpu_used') ?? 0;
        $avgMemory = Server::where('status', 'online')->avg('memory_used') ?? 0;
        $runningContainers = Container::where('status', 'running')->count();
        $unhealthyContainers = Container::where('health_status', 'unhealthy')->count();
        $failedToday = Deployment::whereDate('created_at', today())->where('status', 'failed')->count();
        $successToday = Deployment::whereDate('created_at', today())->where('status', 'running')->count();

        return [
            Stat::make('Servidores Online', $onlineServers.'/'.$totalServers)
                ->description($totalServers - $onlineServers > 0 ? ($totalServers - $onlineServers).' offline' : 'Todos online')
                ->descriptionIcon($totalServers - $onlineServers > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($onlineServers === $totalServers ? 'success' : 'danger')
                ->chart([7, 3, 4, 5, 6, 3, 5]),

            Stat::make('CPU Médio', number_format($avgCpu, 1).'%')
                ->description($avgCpu > 80 ? 'Alta utilização' : 'Utilização normal')
                ->descriptionIcon($avgCpu > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-cpu-chip')
                ->color($avgCpu > 80 ? 'danger' : ($avgCpu > 60 ? 'warning' : 'success'))
                ->chart([65, 59, 80, 81, 56, 55, (int) $avgCpu]),

            Stat::make('Memória Média', number_format($avgMemory, 1).'%')
                ->description($avgMemory > 80 ? 'Alta utilização' : 'Utilização normal')
                ->descriptionIcon($avgMemory > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-circle-stack')
                ->color($avgMemory > 80 ? 'danger' : ($avgMemory > 60 ? 'warning' : 'success'))
                ->chart([45, 52, 48, 61, 58, 55, (int) $avgMemory]),

            Stat::make('Containers', $runningContainers)
                ->description($unhealthyContainers > 0 ? $unhealthyContainers.' não saudáveis' : 'Todos saudáveis')
                ->descriptionIcon($unhealthyContainers > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-heart')
                ->color($unhealthyContainers > 0 ? 'warning' : 'success'),

            Stat::make('Deploys com Sucesso', $successToday)
                ->description('Hoje')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Deploys Falharam', $failedToday)
                ->description('Hoje')
                ->descriptionIcon($failedToday > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($failedToday > 0 ? 'danger' : 'success'),
        ];
    }
}
