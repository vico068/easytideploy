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
        // Contadores atuais
        $totalApplications = Application::count();
        $activeApplications = Application::where('status', 'active')->count();
        $runningContainers = Container::where('status', 'running')->count();
        $healthyContainers = Container::where('health_status', 'healthy')->count();
        $onlineServers = Server::where('status', 'online')->count();
        $totalServers = Server::count();
        $todayDeployments = Deployment::whereDate('created_at', today())->count();
        $failedDeployments = Deployment::whereDate('created_at', today())->where('status', 'failed')->count();

        // Tendências dos últimos 7 dias para mini charts
        $deploymentsLast7Days = Deployment::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();

        // Preencher com zeros se não houver dados suficientes
        $deploymentsChart = array_pad($deploymentsLast7Days, 7, 0);
        $deploymentsChart = array_slice($deploymentsChart, -7);

        // Containers histórico (simulado - pode ser substituído por dados reais se houver tabela de histórico)
        $containersChart = [$runningContainers];
        for ($i = 6; $i >= 1; $i--) {
            $containersChart[] = Container::where('created_at', '<=', now()->subDays($i))->count();
        }

        return [
            Stat::make('Aplicações', $activeApplications.'/'.$totalApplications)
                ->description('Ativas / Total')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info')
                ->chart(array_fill(0, 7, $activeApplications)),

            Stat::make('Containers', $runningContainers)
                ->description($healthyContainers.' saudáveis')
                ->descriptionIcon('heroicon-m-heart')
                ->color($healthyContainers === $runningContainers ? 'success' : 'warning')
                ->chart($containersChart),

            Stat::make('Servidores', $onlineServers.'/'.$totalServers)
                ->description('Online / Total')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($onlineServers === $totalServers ? 'success' : 'warning')
                ->chart(array_fill(0, 7, $onlineServers)),

            Stat::make('Deployments hoje', $todayDeployments)
                ->description($failedDeployments > 0 ? $failedDeployments.' falharam' : 'Sem falhas')
                ->descriptionIcon($failedDeployments > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($failedDeployments > 0 ? 'danger' : 'success')
                ->chart($deploymentsChart),
        ];
    }
}
