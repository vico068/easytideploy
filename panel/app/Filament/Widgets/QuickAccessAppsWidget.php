<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class QuickAccessAppsWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-access-apps-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public string $userId = '';

    public function mount(): void
    {
        $this->userId = auth()->id() ?? '';
    }

    #[On('echo-private:user.{userId},ContainerStatusChanged')]
    public function onContainerStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }

    #[On('echo-private:user.{userId},DeploymentStatusChanged')]
    public function onDeploymentStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }

    protected function getViewData(): array
    {
        $apps = Application::where('user_id', auth()->id())
            ->withCount(['containers' => fn ($q) => $q->where('status', 'running')])
            ->with(['containers' => fn ($q) => $q->where('status', 'running')->select('id', 'application_id', 'cpu_usage', 'memory_usage')])
            ->latest()
            ->limit(6)
            ->get();

        return ['apps' => $apps];
    }

    public static function getEditUrl(Application $app): string
    {
        return ApplicationResource::getUrl('edit', ['record' => $app]);
    }
}
