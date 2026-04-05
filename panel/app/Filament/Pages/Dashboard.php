<?php

namespace App\Filament\Pages;

use App\Models\Application;
use App\Models\Container;
use App\Models\Deployment;
use App\Models\Server;
use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

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

        // ── Atividade de deploys (últimos 14 dias) ───────────────────────────
        $deployActivity = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )
            ->where('created_at', '>=', now()->subDays(13))
            ->selectRaw("DATE(created_at) as date, COUNT(*) as total,
                         SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as success,
                         SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $activityLabels = [];
        $activitySuccess = [];
        $activityFailed = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $activityLabels[] = now()->subDays($i)->format('d/m');
            $row = $deployActivity->get($date);
            $activitySuccess[] = (int) ($row?->success ?? 0);
            $activityFailed[]  = (int) ($row?->failed ?? 0);
        }

        // ── Apps recentes (para cards de acesso rápido) ──────────────────────
        $recentApps = Application::where('user_id', $userId)
            ->withCount(['containers' => fn ($q) => $q->where('status', 'running')])
            ->with(['containers' => fn ($q) => $q->where('status', 'running')
                ->select('id', 'application_id', 'cpu_usage', 'memory_usage')])
            ->latest()
            ->limit(4)
            ->get();

        // ── Deployments recentes (timeline) ──────────────────────────────────
        $recentDeployments = Deployment::whereHas(
            'application', fn ($q) => $q->where('user_id', $userId)
        )
            ->with('application')
            ->latest()
            ->limit(8)
            ->get();

        // ── Saúde dos servidores ─────────────────────────────────────────────
        $servers = Server::orderBy('name')->get();

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
            'activityLabels',
            'activitySuccess',
            'activityFailed',
            'recentApps',
            'recentDeployments',
            'servers',
        );
    }

    public function getWidgets(): array
    {
        return [];
    }
}
