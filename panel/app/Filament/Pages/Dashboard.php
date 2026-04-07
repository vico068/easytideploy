<?php

namespace App\Filament\Pages;

use App\Models\Application;
use App\Models\Container;
use App\Models\Deployment;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function onDeploymentStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }

    public function onContainerStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }

    protected function getListeners(): array
    {
        $userId = (string) (auth()->id() ?? '');
        if ($userId === '') {
            return [];
        }

        return [
            "echo-private:user.{$userId},DeploymentStatusChanged" => 'onDeploymentStatusChanged',
            "echo-private:user.{$userId},ContainerStatusChanged" => 'onContainerStatusChanged',
        ];
    }

    public function getViewData(): array
    {
        $userId = auth()->id();
        $user   = auth()->user();

        // ── Stats do usuário ─────────────────────────────────────────────────
        $totalApps = Application::where('user_id', $userId)->count();
        $activeApps = Application::where('user_id', $userId)->where('status', 'active')->count();

        $runningContainers = Container::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->where('status', 'running')->count();

        $healthyContainers = Container::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->where('health_status', 'healthy')->count();

        $todayDeploys = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->whereDate('created_at', today())->count();

        $failedDeploys = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->where('status', 'failed')
         ->where('created_at', '>=', now()->subDays(7))
         ->count();

        $totalDeploys = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )->where('status', 'running')->count();

        // ── Deployments recentes (timeline) ──────────────────────────────────
        $recentDeployments = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )
            ->with('application')
            ->latest()
            ->limit(8)
            ->get();

        // ── Saudação ─────────────────────────────────────────────────────────
        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Bom dia',
            $hour < 18 => 'Boa tarde',
            default    => 'Boa noite',
        };

        return compact(
            'user',
            'greeting',
            'totalApps',
            'activeApps',
            'runningContainers',
            'healthyContainers',
            'todayDeploys',
            'failedDeploys',
            'totalDeploys',
            'recentDeployments',
        );
    }

    public function getWidgets(): array
    {
        return [];
    }
}
