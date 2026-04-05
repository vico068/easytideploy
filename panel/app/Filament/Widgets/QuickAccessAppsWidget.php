<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use Filament\Widgets\Widget;

class QuickAccessAppsWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-access-apps-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

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
