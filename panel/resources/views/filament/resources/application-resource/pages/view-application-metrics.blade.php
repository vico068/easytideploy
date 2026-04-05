<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period selector --}}
        <div class="flex items-center space-x-2 bg-white dark:bg-gray-800 rounded-xl p-2 shadow-sm border dark:border-gray-700 inline-flex">
            @foreach(['1h' => '1 hora', '6h' => '6 horas', '24h' => '24 horas', '7d' => '7 dias', '30d' => '30 dias'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    @class([
                        'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200',
                        'bg-gradient-to-r from-brand-600 to-cyan-500 text-white shadow-md' => $period === $key,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700/50 dark:text-gray-300 dark:hover:bg-gray-700' => $period !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Stats cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-brand-200 dark:border-brand-900/30 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">CPU Médio</div>
                    <x-heroicon-o-cpu-chip class="w-6 h-6 text-brand-500 opacity-70" />
                </div>
                <div class="text-3xl font-bold bg-gradient-to-r from-brand-600 to-brand-500 bg-clip-text text-transparent">
                    {{ number_format($record->resourceUsages()->forPeriod($period)->avg('cpu_percent') ?? 0, 1) }}%
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-cyan-200 dark:border-cyan-900/30 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Memória Média</div>
                    <x-heroicon-o-circle-stack class="w-6 h-6 text-cyan-500 opacity-70" />
                </div>
                <div class="text-3xl font-bold bg-gradient-to-r from-cyan-600 to-cyan-500 bg-clip-text text-transparent">
                    {{ number_format($record->resourceUsages()->forPeriod($period)->avg('memory_percent') ?? 0, 1) }}%
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-emerald-200 dark:border-emerald-900/30 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Requisições HTTP</div>
                    <x-heroicon-o-globe-alt class="w-6 h-6 text-emerald-500 opacity-70" />
                </div>
                <div class="text-3xl font-bold text-emerald-600">
                    {{ number_format($httpTotals->total_requests ?? 0) }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-red-200 dark:border-red-900/30 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Erros (4xx+5xx)</div>
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-500 opacity-70" />
                </div>
                <div class="text-3xl font-bold text-red-600">
                    {{ number_format(($httpTotals->total_4xx ?? 0) + ($httpTotals->total_5xx ?? 0)) }}
                </div>
            </div>
        </div>

        {{-- CPU & Memory Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Uso de Recursos</h3>
                <div class="flex items-center space-x-4 text-sm text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-[#0d8bfa]"></span>
                        CPU
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-[#06b6d4]"></span>
                        Memória
                    </span>
                </div>
            </div>
            @if(count($resourceChartData['labels']) > 0)
                <div class="h-64">
                    <canvas id="resourceChart"></canvas>
                </div>
            @else
                <div class="h-64 flex items-center justify-center text-gray-500 bg-gray-50 dark:bg-gray-900/30 rounded-lg border-2 border-dashed dark:border-gray-700">
                    <div class="text-center">
                        <x-heroicon-o-chart-bar class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        <p class="font-medium">Sem dados de recursos para o período selecionado</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- HTTP Requests Chart --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Requisições HTTP</h3>
                <div class="flex items-center space-x-4 text-sm text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-[#0d8bfa]"></span>
                        Total
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-[#10b981]"></span>
                        Sucesso
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-[#ef4444]"></span>
                        Erros
                    </span>
                </div>
            </div>
            @if(count($httpChartData['labels']) > 0)
                <div class="h-64">
                    <canvas id="httpChart"></canvas>
                </div>
            @else
                <div class="h-64 flex items-center justify-center text-gray-500 bg-gray-50 dark:bg-gray-900/30 rounded-lg border-2 border-dashed dark:border-gray-700">
                    <div class="text-center">
                        <x-heroicon-o-globe-alt class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        <p class="font-medium">Sem dados de requisições HTTP para o período selecionado</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Containers table --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Containers Ativos</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Container</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Servidor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Memória</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($record->containers as $container)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <x-heroicon-m-cube class="w-5 h-5 text-gray-400 mr-2" />
                                        <span class="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $container->container_name ?? $container->name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center">
                                        <x-heroicon-m-server class="w-4 h-4 mr-1.5 text-gray-400" />
                                        {{ $container->server->name ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-progress-bar
                                        :value="$container->cpu_usage ?? 0"
                                        :label="number_format($container->cpu_usage ?? 0, 1) . '%'"
                                        variant="auto"
                                        showValue="true"
                                        height="h-2"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-progress-bar
                                        :value="$container->memory_usage ?? 0"
                                        :label="number_format($container->memory_usage ?? 0, 1) . '%'"
                                        variant="auto"
                                        showValue="true"
                                        height="h-2"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-status-badge :status="$container->status" size="sm" dot="true" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

            // Resource Chart
            const resourceCanvas = document.getElementById('resourceChart');
            if (resourceCanvas) {
                new Chart(resourceCanvas, {
                    type: 'line',
                    data: {
                        labels: @json($resourceChartData['labels']),
                        datasets: [
                            {
                                label: 'CPU (%)',
                                data: @json($resourceChartData['cpu']),
                                borderColor: '#0d8bfa',
                                backgroundColor: 'rgba(13, 139, 250, 0.1)',
                                fill: true,
                                tension: 0.3,
                            },
                            {
                                label: 'Memória (%)',
                                data: @json($resourceChartData['memory']),
                                borderColor: '#06b6d4',
                                backgroundColor: 'rgba(6, 182, 212, 0.1)',
                                fill: true,
                                tension: 0.3,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { min: 0, max: 100, grid: { color: gridColor } },
                            x: { grid: { color: gridColor } }
                        },
                        plugins: { legend: { display: true } }
                    }
                });
            }

            // HTTP Chart
            const httpCanvas = document.getElementById('httpChart');
            if (httpCanvas) {
                new Chart(httpCanvas, {
                    type: 'line',
                    data: {
                        labels: @json($httpChartData['labels']),
                        datasets: [
                            {
                                label: 'Total',
                                data: @json($httpChartData['total']),
                                borderColor: '#0d8bfa',
                                backgroundColor: 'rgba(13, 139, 250, 0.1)',
                                fill: true,
                                tension: 0.3,
                            },
                            {
                                label: 'Sucesso (2xx)',
                                data: @json($httpChartData['success']),
                                borderColor: '#10b981',
                                fill: false,
                                tension: 0.3,
                            },
                            {
                                label: 'Erros (4xx+5xx)',
                                data: @json($httpChartData['errors']),
                                borderColor: '#ef4444',
                                fill: false,
                                tension: 0.3,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { min: 0, beginAtZero: true, grid: { color: gridColor } },
                            x: { grid: { color: gridColor } }
                        },
                        plugins: { legend: { display: true } }
                    }
                });
            }
        });
    </script>
    @endpush
</x-filament-panels::page>
