<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\Container;
use App\Models\Deployment;
use App\Models\Server;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalApplications = Application::count();
        $activeApplications = Application::where('status', 'active')->count();
        $runningContainers = Container::where('status', 'running')->count();
        $healthyContainers = Container::where('health_status', 'healthy')->count();
        $onlineServers = Server::where('status', 'online')->count();
        $totalServers = Server::count();
        $todayDeployments = Deployment::whereDate('created_at', today())->count();
        $failedDeployments = Deployment::whereDate('created_at', today())->where('status', 'failed')->count();

        return [
            Stat::make('Aplicações', $activeApplications.'/'.$totalApplications)
                ->description('Ativas / Total')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Containers', $runningContainers)
                ->description($healthyContainers.' saudáveis')
                ->descriptionIcon('heroicon-m-heart')
                ->color($healthyContainers === $runningContainers ? 'success' : 'warning'),

            Stat::make('Servidores', $onlineServers.'/'.$totalServers)
                ->description('Online / Total')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($onlineServers === $totalServers ? 'success' : 'warning'),

            Stat::make('Deployments hoje', $todayDeployments)
                ->description($failedDeployments > 0 ? $failedDeployments.' falharam' : 'Sem falhas')
                ->descriptionIcon($failedDeployments > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($failedDeployments > 0 ? 'danger' : 'success'),
        ];
    }
}
