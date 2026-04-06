<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\Container;
use App\Models\Deployment;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class UserStatsWidget extends Widget
{
    protected static string $view = 'filament.widgets.user-stats-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public int $userId = 0;

    public function mount(): void
    {
        $this->userId = auth()->id() ?? 0;
    }

    #[On('echo-private:user.{userId},DeploymentStatusChanged')]
    public function onDeploymentStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }

    #[On('echo-private:user.{userId},ContainerStatusChanged')]
    public function onContainerStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }

    protected function getViewData(): array
    {
        $userId = auth()->id();

        $totalApps = Application::where('user_id', $userId)->count();

        $runningContainers = Container::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->where('status', 'running')->count();

        $successDeploys = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->where('status', 'running')->count();

        $todayDeploys = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->whereDate('created_at', today())->count();

        $failedDeploys = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->where('status', 'failed')->count();

        // Trend dos últimos 7 dias
        $deploysTrend = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )
            ->where('created_at', '>=', now()->subDays(6))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $trend[] = $deploysTrend[$date] ?? 0;
        }

        return compact(
            'totalApps',
            'runningContainers',
            'successDeploys',
            'todayDeploys',
            'failedDeploys',
            'trend'
        );
    }
}
