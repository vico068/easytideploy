<?php

namespace App\Filament\Widgets;

use App\Models\HttpMetric;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class StatusCodesChart extends ChartWidget
{
    protected static ?string $heading = 'Distribuição de Status Codes';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = true;

    public ?string $filter = '24h';

    protected function getFilters(): ?array
    {
        return [
            '1h' => 'Última hora',
            '24h' => 'Últimas 24 horas',
            '7d' => 'Últimos 7 dias',
        ];
    }

    protected function getData(): array
    {
        $since = match ($this->filter) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            default => now()->subDay(),
        };

        $totals = HttpMetric::where('recorded_at', '>=', $since)
            ->select([
                DB::raw('COALESCE(SUM(requests_2xx), 0) as total_2xx'),
                DB::raw('COALESCE(SUM(requests_3xx), 0) as total_3xx'),
                DB::raw('COALESCE(SUM(requests_4xx), 0) as total_4xx'),
                DB::raw('COALESCE(SUM(requests_5xx), 0) as total_5xx'),
            ])
            ->first();

        return [
            'datasets' => [
                [
                    'data' => [
                        $totals->total_2xx ?? 0,
                        $totals->total_3xx ?? 0,
                        $totals->total_4xx ?? 0,
                        $totals->total_5xx ?? 0,
                    ],
                    'backgroundColor' => [
                        '#10b981', // 2xx - success green
                        '#06b6d4', // 3xx - info cyan
                        '#f59e0b', // 4xx - warning amber
                        '#ef4444', // 5xx - danger red
                    ],
                ],
            ],
            'labels' => ['2xx Sucesso', '3xx Redirect', '4xx Client Error', '5xx Server Error'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
