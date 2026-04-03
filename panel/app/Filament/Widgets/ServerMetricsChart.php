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

        $servers = Server::where('status', 'online')->get();

        $datasets = [];
        $colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
        $labels = [];

        foreach ($servers as $index => $server) {
            $data = ResourceUsage::where('recorded_at', '>=', $since)
                ->whereHas('container', function ($q) use ($server) {
                    $q->where('server_id', $server->id);
                })
                ->select([
                    DB::raw("date_trunc('{$interval}', recorded_at) as period"),
                    DB::raw('AVG(cpu_usage) as avg_cpu'),
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
