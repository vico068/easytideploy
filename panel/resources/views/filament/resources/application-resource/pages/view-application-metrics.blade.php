<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Page header --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <a href="{{ $backUrl }}"
               class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors self-start">
                <x-heroicon-m-chevron-left class="w-4 h-4" />
                Voltar
            </a>
            <div class="flex-1 sm:ml-2">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                            <x-heroicon-o-chart-bar class="w-5 h-5 inline-block mb-0.5 text-brand-500 mr-1" />
                            {{ $record->name }}
                        </h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Métricas de desempenho e requisições</p>
                    </div>
                    {{-- Period selector --}}
                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 self-start sm:self-auto">
                        @foreach(['1h' => '1h', '6h' => '6h', '24h' => '24h', '7d' => '7d', '30d' => '30d'] as $key => $label)
                            <button
                                wire:click="setPeriod('{{ $key }}')"
                                @class([
                                    'px-3 py-1.5 rounded-md text-sm font-medium transition-all duration-150',
                                    'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' => $period === $key,
                                    'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' => $period !== $key,
                                ])
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU Médio</span>
                    <x-heroicon-o-cpu-chip class="w-4 h-4 text-brand-500 opacity-80" />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($record->resourceUsages()->forPeriod($period)->avg('cpu_percent') ?? 0, 1) }}%
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Memória Média</span>
                    <x-heroicon-o-circle-stack class="w-4 h-4 text-cyan-500 opacity-80" />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($record->resourceUsages()->forPeriod($period)->avg('memory_percent') ?? 0, 1) }}%
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Requisições</span>
                    <x-heroicon-o-globe-alt class="w-4 h-4 text-emerald-500 opacity-80" />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($httpTotals->total_requests ?? 0) }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Erros</span>
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-500 opacity-80" />
                </div>
                <div class="text-2xl font-bold {{ (($httpTotals->total_4xx ?? 0) + ($httpTotals->total_5xx ?? 0)) > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                    {{ number_format(($httpTotals->total_4xx ?? 0) + ($httpTotals->total_5xx ?? 0)) }}
                </div>
            </div>
        </div>

        {{-- Charts row --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            {{-- CPU & Memory chart --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Uso de Recursos</h3>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-brand-500 inline-block"></span> CPU</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-cyan-500 inline-block"></span> RAM</span>
                    </div>
                </div>
                @if(count($resourceChartData['labels']) > 0)
                    <div class="h-52"><canvas id="resourceChart"></canvas></div>
                @else
                    <div class="h-52 flex flex-col items-center justify-center text-gray-400 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed dark:border-gray-700">
                        <x-heroicon-o-chart-bar class="w-10 h-10 mb-2 opacity-30" />
                        <p class="text-sm">Sem dados no período</p>
                    </div>
                @endif
            </div>

            {{-- HTTP requests chart --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Requisições HTTP</h3>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span> Total</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> 2xx</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span> Erros</span>
                    </div>
                </div>
                @if(count($httpChartData['labels']) > 0)
                    <div class="h-52"><canvas id="httpChart"></canvas></div>
                @else
                    <div class="h-52 flex flex-col items-center justify-center text-gray-400 bg-gray-50 dark:bg-gray-900/20 rounded-lg border-2 border-dashed dark:border-gray-700">
                        <x-heroicon-o-globe-alt class="w-10 h-10 mb-2 opacity-30" />
                        <p class="text-sm">Sem dados no período</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Containers table --}}
        @if($record->containers->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Containers Ativos</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/30">
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Container</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Servidor</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-40">CPU</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-40">Memória</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($record->containers as $container)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/20 transition-colors">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-m-cube class="w-4 h-4 text-gray-400 shrink-0" />
                                        <span class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $container->container_name ?? $container->name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $container->server->name ?? 'N/A' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <x-progress-bar
                                        :value="$container->cpu_usage ?? 0"
                                        :label="number_format($container->cpu_usage ?? 0, 1) . '%'"
                                        variant="auto" showValue="true" height="h-2"
                                    />
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <x-progress-bar
                                        :value="$container->memory_usage ?? 0"
                                        :label="number_format($container->memory_usage ?? 0, 1) . '%'"
                                        variant="auto" showValue="true" height="h-2"
                                    />
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <x-status-badge :status="$container->status" size="sm" dot="true" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dark = document.documentElement.classList.contains('dark');
            const grid = dark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
            const tick = dark ? '#9ca3af' : '#6b7280';

            const sharedOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: tick, maxTicksLimit: 8 } },
                    y: { grid: { color: grid }, ticks: { color: tick } }
                }
            };

            const rc = document.getElementById('resourceChart');
            if (rc) {
                new Chart(rc, {
                    type: 'line',
                    data: {
                        labels: @json($resourceChartData['labels']),
                        datasets: [
                            { label: 'CPU %', data: @json($resourceChartData['cpu']), borderColor: '#0d8bfa', backgroundColor: 'rgba(13,139,250,0.08)', fill: true, tension: 0.3, pointRadius: 2 },
                            { label: 'RAM %', data: @json($resourceChartData['memory']), borderColor: '#06b6d4', backgroundColor: 'rgba(6,182,212,0.08)', fill: true, tension: 0.3, pointRadius: 2 }
                        ]
                    },
                    options: { ...sharedOptions, scales: { ...sharedOptions.scales, y: { ...sharedOptions.scales.y, min: 0, max: 100 } } }
                });
            }

            const hc = document.getElementById('httpChart');
            if (hc) {
                new Chart(hc, {
                    type: 'line',
                    data: {
                        labels: @json($httpChartData['labels']),
                        datasets: [
                            { label: 'Total', data: @json($httpChartData['total']), borderColor: '#0d8bfa', backgroundColor: 'rgba(13,139,250,0.08)', fill: true, tension: 0.3, pointRadius: 2 },
                            { label: 'Sucesso (2xx)', data: @json($httpChartData['success']), borderColor: '#10b981', fill: false, tension: 0.3, pointRadius: 2 },
                            { label: 'Erros', data: @json($httpChartData['errors']), borderColor: '#ef4444', fill: false, tension: 0.3, pointRadius: 2 }
                        ]
                    },
                    options: {
                        ...sharedOptions,
                        plugins: { legend: { display: true, position: 'bottom', labels: { color: tick, boxWidth: 12, padding: 12 } } },
                        scales: { ...sharedOptions.scales, y: { ...sharedOptions.scales.y, min: 0, beginAtZero: true } }
                    }
                });
            }
        });
    </script>
    @endpush
</x-filament-panels::page>
