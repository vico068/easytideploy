<?php

namespace App\Filament\Widgets;

use App\Models\HttpMetric;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RequestsChart extends ChartWidget
{
    protected static ?string $heading = 'Requisições HTTP';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = true;

    public ?string $filter = '24h';

    protected function getFilters(): ?array
    {
        return [
            '1h' => 'Última hora',
            '6h' => 'Últimas 6 horas',
            '24h' => 'Últimas 24 horas',
            '7d' => 'Últimos 7 dias',
        ];
    }

    protected function getData(): array
    {
        $since = match ($this->filter) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            default => now()->subDay(),
        };

        // Bucket size in seconds
        $bucketSeconds = match ($this->filter) {
            '1h' => 300,    // 5 minutes
            '6h' => 1800,   // 30 minutes
            '24h' => 3600,  // 1 hour
            '7d' => 21600,  // 6 hours
            default => 3600,
        };

        $data = HttpMetric::where('recorded_at', '>=', $since)
            ->select([
                DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                DB::raw('SUM(total_requests) as total'),
                DB::raw('SUM(requests_2xx) as success'),
                DB::raw('SUM(requests_4xx + requests_5xx) as errors'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $labels = $data->pluck('period')->map(function ($date) {
            return \Carbon\Carbon::parse($date)->format($this->filter === '7d' ? 'd/m H:i' : 'H:i');
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => $data->pluck('total')->toArray(),
                    'borderColor' => '#0d8bfa',
                    'backgroundColor' => 'rgba(13, 139, 250, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Sucesso (2xx)',
                    'data' => $data->pluck('success')->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Erros (4xx+5xx)',
                    'data' => $data->pluck('errors')->toArray(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'min' => 0,
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
