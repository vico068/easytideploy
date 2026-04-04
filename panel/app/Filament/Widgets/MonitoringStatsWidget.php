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

    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        // Estatísticas atuais
        $onlineServers = Server::where('status', 'online')->count();
        $totalServers = Server::count();
        $avgCpu = Server::where('status', 'online')->avg('cpu_used') ?? 0;
        $avgMemory = Server::where('status', 'online')->avg('memory_used') ?? 0;
        $runningContainers = Container::where('status', 'running')->count();
        $unhealthyContainers = Container::where('health_status', 'unhealthy')->count();
        $failedToday = Deployment::whereDate('created_at', today())->where('status', 'failed')->count();
        $successToday = Deployment::whereDate('created_at', today())->where('status', 'running')->count();

        // Dados históricos dos últimos 7 dias para mini charts
        $serversHistoric = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $serversHistoric[] = Server::where('status', 'online')
                ->where('created_at', '<=', $date)
                ->count();
        }

        $cpuHistoric = [];
        $memoryHistoric = [];
        for ($i = 6; $i >= 0; $i--) {
            // Em produção, isso deveria vir de uma tabela de métricas históricas
            // Por ora, vamos usar valores simulados baseados na média atual
            $cpuHistoric[] = max(0, min(100, $avgCpu + rand(-10, 10)));
            $memoryHistoric[] = max(0, min(100, $avgMemory + rand(-10, 10)));
        }

        return [
            Stat::make('Servidores Online', $onlineServers.'/'.$totalServers)
                ->description($totalServers - $onlineServers > 0 ? ($totalServers - $onlineServers).' offline' : 'Todos online')
                ->descriptionIcon($totalServers - $onlineServers > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($onlineServers === $totalServers ? 'success' : 'danger')
                ->chart($serversHistoric),

            Stat::make('CPU Médio', number_format($avgCpu, 1).'%')
                ->description($avgCpu > 80 ? 'Alta utilização' : 'Utilização normal')
                ->descriptionIcon($avgCpu > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-cpu-chip')
                ->color($avgCpu > 80 ? 'danger' : ($avgCpu > 60 ? 'warning' : 'success'))
                ->chart($cpuHistoric),

            Stat::make('Memória Média', number_format($avgMemory, 1).'%')
                ->description($avgMemory > 80 ? 'Alta utilização' : 'Utilização normal')
                ->descriptionIcon($avgMemory > 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-circle-stack')
                ->color($avgMemory > 80 ? 'danger' : ($avgMemory > 60 ? 'warning' : 'success'))
                ->chart($memoryHistoric),

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
