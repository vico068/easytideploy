<?php

namespace App\Filament\Widgets;

use App\Models\ResourceUsage;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ResourceUsageChart extends ChartWidget
{
    protected static ?string $heading = 'Uso de Recursos';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '1h';

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
            default => now()->subHour(),
        };

        $interval = match ($this->filter) {
            '1h' => '5 minutes',
            '6h' => '30 minutes',
            '24h' => '1 hour',
            '7d' => '6 hours',
            default => '5 minutes',
        };

        $data = ResourceUsage::where('recorded_at', '>=', $since)
            ->select([
                DB::raw("date_trunc('{$interval}', recorded_at) as period"),
                DB::raw('AVG(cpu_usage) as avg_cpu'),
                DB::raw('AVG(memory_usage) as avg_memory'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $labels = $data->pluck('period')->map(function ($date) {
            return \Carbon\Carbon::parse($date)->format('H:i');
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'CPU (%)',
                    'data' => $data->pluck('avg_cpu')->map(fn ($v) => round($v, 2))->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Memória (%)',
                    'data' => $data->pluck('avg_memory')->map(fn ($v) => round($v, 2))->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
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
                    'max' => 100,
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
