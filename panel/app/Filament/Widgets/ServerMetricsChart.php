<?php

namespace App\Filament\Widgets;

use App\Models\ResourceUsage;
use App\Models\Server;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ServerMetricsChart extends ChartWidget
{
    protected static ?string $heading = 'Métricas dos Servidores';

    protected static ?int $sort = 3;

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

        // Bucket size in seconds
        $bucketSeconds = match ($this->filter) {
            '1h' => 300,    // 5 minutes
            '6h' => 1800,   // 30 minutes
            '24h' => 3600,  // 1 hour
            '7d' => 21600,  // 6 hours
            default => 300,
        };

        $servers = Server::where('status', 'online')->get();

        $datasets = [];
        $colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
        $labels = [];

        foreach ($servers as $index => $server) {
            $data = ResourceUsage::where('recorded_at', '>=', $since)
                ->where('server_id', $server->id)
                ->whereNull('container_id')
                ->select([
                    DB::raw("to_timestamp(floor(extract(epoch from recorded_at) / {$bucketSeconds}) * {$bucketSeconds}) as period"),
                    DB::raw('AVG(cpu_percent) as avg_cpu'),
                ])
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            if ($labels === [] && $data->count() > 0) {
                $labels = $data->pluck('period')->map(function ($date) {
                    return \Carbon\Carbon::parse($date)->format('H:i');
                })->toArray();
            }

            $color = $colors[$index % count($colors)];

            $datasets[] = [
                'label' => $server->name.' (CPU)',
                'data' => $data->pluck('avg_cpu')->map(fn ($v) => round($v ?? 0, 2))->toArray(),
                'borderColor' => $color,
                'backgroundColor' => 'transparent',
                'tension' => 0.3,
            ];
        }

        return [
            'datasets' => $datasets,
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
                    'title' => [
                        'display' => true,
                        'text' => 'CPU %',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
