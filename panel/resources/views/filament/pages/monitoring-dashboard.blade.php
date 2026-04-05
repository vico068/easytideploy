<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Top bar: title + period selector --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Monitoramento</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Visão geral da infraestrutura em tempo real</p>
            </div>
            <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 self-start sm:self-auto">
                @foreach(['1h' => '1h', '6h' => '6h', '24h' => '24h', '7d' => '7d'] as $key => $label)
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

        {{-- Summary stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Servers --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Servidores</span>
                    <x-heroicon-m-server class="w-4 h-4 {{ $onlineServers === $totalServers ? 'text-emerald-500' : 'text-red-500' }}" />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $onlineServers }}<span class="text-sm font-normal text-gray-400">/{{ $totalServers }}</span></div>
                <div class="text-xs mt-1 {{ $onlineServers === $totalServers ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $onlineServers === $totalServers ? 'Todos online' : ($totalServers - $onlineServers).' offline' }}
                </div>
            </div>

            {{-- CPU --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">CPU Médio</span>
                    <x-heroicon-m-cpu-chip class="w-4 h-4 {{ $avgCpu > 80 ? 'text-red-500' : 'text-brand-500' }}" />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $avgCpu }}%</div>
                <div class="text-xs mt-1 {{ $avgCpu > 80 ? 'text-red-600' : ($avgCpu > 60 ? 'text-amber-600' : 'text-emerald-600') }}">
                    {{ $avgCpu > 80 ? 'Alta utilização' : ($avgCpu > 60 ? 'Utilização elevada' : 'Normal') }}
                </div>
            </div>

            {{-- Memory --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Memória Média</span>
                    <x-heroicon-m-circle-stack class="w-4 h-4 {{ $avgMemory > 80 ? 'text-red-500' : 'text-cyan-500' }}" />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $avgMemory }}%</div>
                <div class="text-xs mt-1 {{ $avgMemory > 80 ? 'text-red-600' : ($avgMemory > 60 ? 'text-amber-600' : 'text-emerald-600') }}">
                    {{ $avgMemory > 80 ? 'Alta utilização' : ($avgMemory > 60 ? 'Utilização elevada' : 'Normal') }}
                </div>
            </div>

            {{-- HTTP --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Requisições HTTP</span>
                    <x-heroicon-m-globe-alt class="w-4 h-4 text-indigo-500" />
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalRequests) }}</div>
                <div class="text-xs mt-1 text-gray-500">
                    @if($period === '1h') última hora
                    @elseif($period === '6h') últimas 6h
                    @elseif($period === '24h') últimas 24h
                    @else últimos 7 dias
                    @endif
                </div>
            </div>
        </div>

        {{-- Server cards grid --}}
        @if($servers->count() > 0)
        <div>
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">Servidores</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($servers as $server)
                    <div @class([
                        'bg-white dark:bg-gray-800 rounded-xl border-2 p-5 transition-shadow hover:shadow-md',
                        'border-emerald-200 dark:border-emerald-800' => $server->status->value === 'online',
                        'border-red-200 dark:border-red-800' => $server->status->value === 'offline',
                        'border-amber-200 dark:border-amber-800' => in_array($server->status->value, ['maintenance', 'draining']),
                    ])>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-server class="w-4 h-4 text-gray-400" />
                                <span class="font-semibold text-gray-900 dark:text-gray-100 text-sm">{{ $server->name }}</span>
                            </div>
                            <x-status-badge :status="$server->status" size="sm" />
                        </div>

                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 mb-4">
                            <span class="font-mono">{{ $server->ip_address }}</span>
                            <span>·</span>
                            <span>{{ $server->containers->count() }} containers</span>
                        </div>

                        <div class="space-y-2">
                            <x-progress-bar :value="$server->cpu_usage ?? 0" label="CPU" :showValue="true" variant="auto" height="h-2" />
                            <x-progress-bar :value="$server->memory_usage ?? 0" label="RAM" :showValue="true" variant="auto" height="h-2" />
                            <x-progress-bar :value="$server->disk_usage ?? 0" label="Disco" :showValue="true" variant="auto" height="h-2" :thresholds="['success' => 60, 'warning' => 85]" />
                        </div>

                        @if($server->last_heartbeat)
                            <div class="mt-3 pt-3 border-t dark:border-gray-700 flex items-center gap-1.5 text-xs text-gray-400">
                                <x-heroicon-m-heart class="w-3 h-3 {{ $server->status->value === 'online' ? 'text-emerald-500' : '' }}" />
                                <span>{{ \Carbon\Carbon::parse($server->last_heartbeat)->diffForHumans() }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Charts row --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            {{-- Resource usage chart --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Uso de Recursos (Servidores)</h3>
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

        {{-- Recent deployments --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-rocket-launch class="w-4 h-4 text-gray-400" />
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Deployments Recentes</h3>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/30">
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aplicação</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Commit</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Quando</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($recentDeployments as $deployment)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/20 transition-colors">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-m-cube class="w-4 h-4 text-gray-400 shrink-0" />
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $deployment->application->name ?? 'N/A' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-xs font-mono text-gray-500 dark:text-gray-400">
                                    {{ substr($deployment->commit_sha ?? '', 0, 7) ?: '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <x-status-badge :status="$deployment->status" size="sm" dot="true" />
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $deployment->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center">
                                    <x-heroicon-o-inbox class="w-10 h-10 mx-auto mb-2 opacity-20 text-gray-400" />
                                    <p class="text-sm text-gray-400">Nenhum deployment recente</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

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

            // Resource chart
            const rc = document.getElementById('resourceChart');
            if (rc) {
                new Chart(rc, {
                    type: 'line',
                    data: {
                        labels: @json($resourceChartData['labels']),
                        datasets: [
                            {
                                label: 'CPU %',
                                data: @json($resourceChartData['cpu']),
                                borderColor: '#0d8bfa',
                                backgroundColor: 'rgba(13,139,250,0.08)',
                                fill: true, tension: 0.3, pointRadius: 2,
                            },
                            {
                                label: 'RAM %',
                                data: @json($resourceChartData['memory']),
                                borderColor: '#06b6d4',
                                backgroundColor: 'rgba(6,182,212,0.08)',
                                fill: true, tension: 0.3, pointRadius: 2,
                            }
                        ]
                    },
                    options: { ...sharedOptions, scales: { ...sharedOptions.scales, y: { ...sharedOptions.scales.y, min: 0, max: 100 } } }
                });
            }

            // HTTP chart
            const hc = document.getElementById('httpChart');
            if (hc) {
                new Chart(hc, {
                    type: 'bar',
                    data: {
                        labels: @json($httpChartData['labels']),
                        datasets: [
                            {
                                label: 'Sucesso (2xx)',
                                data: @json($httpChartData['success']),
                                backgroundColor: 'rgba(16,185,129,0.7)',
                                borderRadius: 3,
                            },
                            {
                                label: 'Erros (4xx+5xx)',
                                data: @json($httpChartData['errors']),
                                backgroundColor: 'rgba(239,68,68,0.7)',
                                borderRadius: 3,
                            }
                        ]
                    },
                    options: {
                        ...sharedOptions,
                        plugins: { legend: { display: true, position: 'bottom', labels: { color: tick, boxWidth: 12, padding: 12 } } },
                        scales: { ...sharedOptions.scales, x: { ...sharedOptions.scales.x, stacked: true }, y: { ...sharedOptions.scales.y, stacked: true, min: 0, beginAtZero: true } }
                    }
                });
            }
        });
    </script>
    @endpush
</x-filament-panels::page>
