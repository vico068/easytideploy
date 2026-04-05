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

    protected static bool $isLazy = true;
    protected static bool $isDiscovered = false;

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

        // Bucket size in seconds (date_trunc only accepts single units like 'hour', 'day')
        $bucketSeconds = match ($this->filter) {
            '1h' => 300,    // 5 minutes
            '6h' => 1800,   // 30 minutes
            '24h' => 3600,  // 1 hour
            '7d' => 21600,  // 6 hours
            default => 300,
        };

        $data = ResourceUsage::where('recorded_at', '>=', $since)
            ->select([
                DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                DB::raw('AVG(cpu_percent) as avg_cpu'),
                DB::raw('AVG(memory_percent) as avg_memory'),
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
                    'borderColor' => '#0d8bfa',
                    'backgroundColor' => 'rgba(13, 139, 250, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Memória (%)',
                    'data' => $data->pluck('avg_memory')->map(fn ($v) => round($v, 2))->toArray(),
                    'borderColor' => '#06b6d4',
                    'backgroundColor' => 'rgba(6, 182, 212, 0.1)',
                    'fill' => true,
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
