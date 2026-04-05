<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\HttpMetric;
use App\Models\ResourceUsage;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class ViewApplicationMetrics extends Page
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.view-application-metrics';

    public Application $record;

    public string $period = '1h';

    public function mount(Application $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return "Métricas - {$this->record->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Voltar')
                ->url(ApplicationResource::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),
        ];
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    protected function getViewData(): array
    {
        $since = match ($this->period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subHour(),
        };

        // Bucket size in seconds (date_trunc only accepts single-unit keywords)
        $bucketSeconds = match ($this->period) {
            '1h' => 300,    // 5 minutes
            '6h' => 1800,   // 30 minutes
            '24h' => 3600,  // 1 hour
            '7d' => 21600,  // 6 hours
            '30d' => 86400, // 1 day
            default => 300,
        };

        // Resource usage chart data
        $resourceData = ResourceUsage::where('application_id', $this->record->id)
            ->where('recorded_at', '>=', $since)
            ->select([
                DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                DB::raw('AVG(cpu_percent) as avg_cpu'),
                DB::raw('AVG(memory_percent) as avg_memory'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // HTTP metrics chart data
        $httpData = HttpMetric::where('application_id', $this->record->id)
            ->where('recorded_at', '>=', $since)
            ->select([
                DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                DB::raw('SUM(total_requests) as total'),
                DB::raw('SUM(requests_2xx) as success'),
                DB::raw('SUM(requests_4xx + requests_5xx) as errors'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // HTTP totals for stats cards
        $httpTotals = HttpMetric::where('application_id', $this->record->id)
            ->where('recorded_at', '>=', $since)
            ->select([
                DB::raw('COALESCE(SUM(total_requests), 0) as total_requests'),
                DB::raw('COALESCE(SUM(requests_2xx), 0) as total_2xx'),
                DB::raw('COALESCE(SUM(requests_4xx), 0) as total_4xx'),
                DB::raw('COALESCE(SUM(requests_5xx), 0) as total_5xx'),
            ])
            ->first();

        $dateFormat = $this->period === '7d' || $this->period === '30d' ? 'd/m H:i' : 'H:i';

        return [
            'period' => $this->period,
            'resourceChartData' => [
                'labels' => $resourceData->pluck('period')->map(fn ($d) => \Carbon\Carbon::parse($d)->format($dateFormat))->toArray(),
                'cpu' => $resourceData->pluck('avg_cpu')->map(fn ($v) => round((float) $v, 2))->toArray(),
                'memory' => $resourceData->pluck('avg_memory')->map(fn ($v) => round((float) $v, 2))->toArray(),
            ],
            'httpChartData' => [
                'labels' => $httpData->pluck('period')->map(fn ($d) => \Carbon\Carbon::parse($d)->format($dateFormat))->toArray(),
                'total' => $httpData->pluck('total')->toArray(),
                'success' => $httpData->pluck('success')->toArray(),
                'errors' => $httpData->pluck('errors')->toArray(),
            ],
            'httpTotals' => $httpTotals,
        ];
    }
}
