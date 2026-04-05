<x-filament-panels::page>
<div class="space-y-6">

    {{-- ============================================================
         HEADER: App selector + Period selector
    ============================================================ --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

        {{-- App selector (Alpine.js dropdown via Livewire) --}}
        <div x-data="{ open: false }" class="relative">
            <button
                @click="open = !open"
                class="flex items-center gap-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700
                       rounded-xl px-4 py-2.5 min-w-[260px] shadow-sm
                       hover:border-sky-300 dark:hover:border-sky-500/50 transition-all duration-200 text-left"
            >
                @if($selectedApp)
                    @php
                        $dot = match($selectedApp->status?->value ?? $selectedApp->status) {
                            'active'    => 'bg-emerald-500',
                            'deploying' => 'bg-amber-500',
                            'failed'    => 'bg-red-500',
                            default     => 'bg-slate-400',
                        };
                    @endphp
                    <div class="flex items-center gap-2 flex-1">
                        <div class="w-2 h-2 rounded-full {{ $dot }}"></div>
                        <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ $selectedApp->name }}</span>
                        <span class="text-xs text-slate-400 truncate">{{ $selectedApp->slug }}</span>
                    </div>
                @else
                    <span class="text-sm text-slate-400 flex-1">Selecione uma aplicação</span>
                @endif
                <x-heroicon-m-chevron-down
                    class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                    ::class="{ 'rotate-180': open }"
                />
            </button>

            <div
                x-show="open"
                @click.outside="open = false"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                class="absolute top-full mt-2 left-0 min-w-[260px] z-50 bg-white dark:bg-slate-800
                       border border-gray-200 dark:border-slate-700 rounded-xl shadow-xl
                       shadow-black/10 overflow-hidden"
                x-cloak
            >
                @forelse($applications as $app)
                    @php
                        $dot = match($app->status?->value ?? $app->status) {
                            'active'    => 'bg-emerald-500',
                            'deploying' => 'bg-amber-500 animate-pulse',
                            'failed'    => 'bg-red-500',
                            default     => 'bg-slate-400',
                        };
                    @endphp
                    <button
                        wire:click="setSelectedApp('{{ $app->id }}')"
                        @click="open = false"
                        class="w-full flex items-center gap-3 px-4 py-3 text-left text-sm
                               hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors duration-150
                               {{ ($selectedApp?->id === $app->id) ? 'bg-sky-50 dark:bg-sky-500/10' : '' }}"
                    >
                        <div class="w-2 h-2 rounded-full {{ $dot }} shrink-0"></div>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $app->name }}</span>
                        <span class="text-xs text-slate-400 ml-auto shrink-0">{{ $app->containers_count }} containers</span>
                    </button>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-slate-400">
                        Nenhuma aplicação encontrada
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Period selector --}}
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-slate-800/80 rounded-xl p-1 self-start md:self-auto">
            @foreach(['1h' => '1h', '6h' => '6h', '24h' => '24h', '7d' => '7d'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    @class([
                        'px-4 py-1.5 rounded-lg text-sm font-medium transition-all duration-150',
                        'bg-white dark:bg-slate-700 text-gray-900 dark:text-white shadow-sm' => $period === $key,
                        'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white' => $period !== $key,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>

    </div>

    @if(! $selectedApp)
        {{-- Estado vazio --}}
        <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-slate-700 p-16 text-center">
            <x-heroicon-o-chart-bar class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-slate-600" />
            <h3 class="text-base font-semibold text-gray-500 dark:text-slate-400 mb-2">Nenhuma aplicação selecionada</h3>
            <p class="text-sm text-gray-400 dark:text-slate-500">Selecione uma aplicação acima para ver as métricas</p>
        </div>
    @else

    {{-- ============================================================
         STATS CARDS
    ============================================================ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Containers --}}
        <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                    border border-gray-100 dark:border-white/5
                    hover:border-sky-200 dark:hover:border-sky-500/30
                    hover:shadow-lg hover:shadow-sky-500/10 transition-all duration-300">
            <div class="absolute inset-0 bg-gradient-to-br from-sky-500/0 to-cyan-500/0
                        group-hover:from-sky-500/5 group-hover:to-cyan-500/5 transition-all duration-500 rounded-2xl"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Containers</span>
                    <div class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-500/10">
                        <x-heroicon-o-server-stack class="w-4 h-4 text-sky-500" />
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $runningContainers }}</div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">rodando</p>
            </div>
        </div>

        {{-- CPU --}}
        <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                    border border-gray-100 dark:border-white/5
                    hover:border-cyan-200 dark:hover:border-cyan-500/30
                    hover:shadow-lg hover:shadow-cyan-500/10 transition-all duration-300">
            <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/0 to-blue-500/0
                        group-hover:from-cyan-500/5 group-hover:to-blue-500/5 transition-all duration-500 rounded-2xl"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">CPU Média</span>
                    <div class="p-1.5 rounded-lg {{ $avgCpu > 80 ? 'bg-red-50 dark:bg-red-500/10' : 'bg-cyan-50 dark:bg-cyan-500/10' }}">
                        <x-heroicon-o-cpu-chip class="w-4 h-4 {{ $avgCpu > 80 ? 'text-red-500' : 'text-cyan-500' }}" />
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $avgCpu }}<span class="text-lg font-medium text-slate-400">%</span></div>
                <p class="text-sm mt-1 {{ $avgCpu > 80 ? 'text-red-500' : ($avgCpu > 60 ? 'text-amber-500' : 'text-emerald-500') }}">
                    {{ $avgCpu > 80 ? 'Alta utilização' : ($avgCpu > 60 ? 'Elevada' : 'Normal') }}
                </p>
            </div>
        </div>

        {{-- RAM --}}
        <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                    border border-gray-100 dark:border-white/5
                    hover:border-violet-200 dark:hover:border-violet-500/30
                    hover:shadow-lg hover:shadow-violet-500/10 transition-all duration-300">
            <div class="absolute inset-0 bg-gradient-to-br from-violet-500/0 to-purple-500/0
                        group-hover:from-violet-500/5 group-hover:to-purple-500/5 transition-all duration-500 rounded-2xl"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">RAM Média</span>
                    <div class="p-1.5 rounded-lg {{ $avgMemory > 80 ? 'bg-red-50 dark:bg-red-500/10' : 'bg-violet-50 dark:bg-violet-500/10' }}">
                        <x-heroicon-o-circle-stack class="w-4 h-4 {{ $avgMemory > 80 ? 'text-red-500' : 'text-violet-500' }}" />
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $avgMemory }}<span class="text-lg font-medium text-slate-400">%</span></div>
                <p class="text-sm mt-1 {{ $avgMemory > 80 ? 'text-red-500' : ($avgMemory > 60 ? 'text-amber-500' : 'text-emerald-500') }}">
                    {{ $avgMemory > 80 ? 'Alta utilização' : ($avgMemory > 60 ? 'Elevada' : 'Normal') }}
                </p>
            </div>
        </div>

        {{-- HTTP Requests --}}
        <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                    border border-gray-100 dark:border-white/5
                    hover:border-emerald-200 dark:hover:border-emerald-500/30
                    hover:shadow-lg hover:shadow-emerald-500/10 transition-all duration-300">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/0 to-green-500/0
                        group-hover:from-emerald-500/5 group-hover:to-green-500/5 transition-all duration-500 rounded-2xl"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Requisições</span>
                    <div class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10">
                        <x-heroicon-o-globe-alt class="w-4 h-4 text-emerald-500" />
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalRequests) }}</div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    @if($period === '1h') última hora
                    @elseif($period === '6h') últimas 6h
                    @elseif($period === '24h') últimas 24h
                    @else últimos 7 dias
                    @endif
                </p>
            </div>
        </div>

    </div>

    {{-- ============================================================
         CHARTS — data in JSON tags, rendered by @script
    ============================================================ --}}
    <script id="httpChartData" type="application/json">@json($httpChartData)</script>
    <script id="resourceChartData" type="application/json">@json($resourceChartData)</script>
    <script id="networkChartData" type="application/json">@json($networkChartData)</script>

    <div class="grid grid-cols-1 gap-5">

        {{-- HTTP Requests Chart --}}
        <div class="bg-white dark:bg-slate-800/50 rounded-2xl border border-gray-100 dark:border-white/5 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Requisições HTTP</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Por código de status</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>2xx</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span>3xx</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>4xx</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>5xx</span>
                </div>
            </div>

            @if(count($httpChartData['labels']) > 0)
                <div class="h-56" id="httpChartContainer" wire:ignore><canvas></canvas></div>
            @else
                <div class="h-56 flex flex-col items-center justify-center text-slate-400 bg-slate-50 dark:bg-slate-900/30 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-700">
                    <x-heroicon-o-globe-alt class="w-10 h-10 mb-2 opacity-30" />
                    <p class="text-sm">Sem dados no período</p>
                </div>
            @endif
        </div>

        {{-- Resources Chart --}}
        <div class="bg-white dark:bg-slate-800/50 rounded-2xl border border-gray-100 dark:border-white/5 p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Uso de Recursos</h3>
                    <p class="text-xs text-slate-400 mt-0.5">CPU e RAM por container</p>
                </div>

                {{-- Container filter --}}
                @if($containers->count() > 0)
                    <select
                        wire:change="setSelectedContainer($event.target.value)"
                        class="text-xs bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600
                               rounded-xl px-3 py-2 text-gray-700 dark:text-slate-200
                               focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20
                               transition-all duration-200"
                    >
                        <option value="">Todos os containers</option>
                        @foreach($containers as $container)
                            <option value="{{ $container->id }}" {{ $selectedContainerId === $container->id ? 'selected' : '' }}>
                                {{ $container->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            @if(isset($resourceChartData['labels']) && count($resourceChartData['labels']) > 0)
                <div class="h-56" id="resourceChartContainer" wire:ignore><canvas></canvas></div>
            @else
                <div class="h-56 flex flex-col items-center justify-center text-slate-400 bg-slate-50 dark:bg-slate-900/30 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-700">
                    <x-heroicon-o-chart-bar class="w-10 h-10 mb-2 opacity-30" />
                    <p class="text-sm">Sem dados no período</p>
                </div>
            @endif
        </div>

        {{-- Network Chart --}}
        <div class="bg-white dark:bg-slate-800/50 rounded-2xl border border-gray-100 dark:border-white/5 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Tráfego de Rede</h3>
                    <p class="text-xs text-slate-400 mt-0.5">RX (download) e TX (upload) por período</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-0.5 bg-blue-500 inline-block rounded"></span>RX</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-0.5 bg-blue-500 inline-block rounded border-dashed border border-blue-500"></span>TX</span>
                </div>
            </div>

            @if(isset($networkChartData['labels']) && count($networkChartData['labels']) > 0)
                <div class="h-56" id="networkChartContainer" wire:ignore><canvas></canvas></div>
            @else
                <div class="h-56 flex flex-col items-center justify-center text-slate-400 bg-slate-50 dark:bg-slate-900/30 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-700">
                    <x-heroicon-o-signal class="w-10 h-10 mb-2 opacity-30" />
                    <p class="text-sm">Sem dados de rede no período</p>
                </div>
            @endif
        </div>

    </div>

    {{-- ============================================================
         LOGS TERMINAL
    ============================================================ --}}
    <div class="bg-slate-900 rounded-2xl border border-slate-700 overflow-hidden shadow-lg" wire:poll.5000ms="refreshLogs">

        {{-- Terminal header --}}
        <div class="bg-slate-800/70 px-5 py-3 border-b border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                {{-- macOS dots --}}
                <div class="flex gap-1.5 shrink-0">
                    <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                </div>
                <span class="text-slate-300 text-sm font-medium">Container Logs</span>
                <span class="text-xs text-slate-500 bg-slate-700/50 px-2 py-0.5 rounded-md">{{ $selectedApp->name }}</span>
            </div>

            <div class="flex items-center gap-3">
                {{-- Container selector --}}
                @if($containers->count() > 0)
                    <select
                        wire:change="setSelectedContainer($event.target.value)"
                        class="text-xs bg-slate-700 border-0 rounded-lg px-3 py-1.5
                               text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/30
                               transition-all duration-200"
                    >
                        <option value="">Todos os containers</option>
                        @foreach($containers as $container)
                            <option value="{{ $container->id }}" {{ $selectedContainerId === $container->id ? 'selected' : '' }}>
                                {{ $container->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                {{-- Auto-refresh indicator --}}
                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span>Auto-refresh 5s</span>
                </div>
            </div>
        </div>

        {{-- Logs area --}}
        <div
            class="p-4 font-mono text-xs max-h-[400px] overflow-y-auto
                   scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent space-y-0.5"
            id="logsContainer"
        >
            @forelse($logs as $log)
                @php
                    $levelColor = match($log->level) {
                        'error', 'critical' => 'text-red-400',
                        'warning'           => 'text-amber-400',
                        'info'              => 'text-sky-400',
                        'debug'             => 'text-slate-500',
                        default             => 'text-slate-400',
                    };
                @endphp
                <div class="flex gap-3 py-0.5 px-2 rounded hover:bg-slate-800/40 transition-colors group">
                    <span class="text-slate-600 shrink-0 tabular-nums">
                        {{ $log->timestamp?->format('H:i:s') ?? '—' }}
                    </span>
                    @if(isset($log->container_name))
                        <span class="text-purple-400/60 shrink-0 text-[10px] font-medium mt-px truncate max-w-[80px]" title="{{ $log->container_name }}">
                            {{ $log->container_name }}
                        </span>
                    @endif
                    <span class="{{ $levelColor }} shrink-0 w-12 uppercase text-[10px] font-bold mt-px">
                        {{ $log->level }}
                    </span>
                    <span class="text-slate-300 break-all">{{ $log->message }}</span>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <x-heroicon-o-document-text class="w-8 h-8 text-slate-700 mb-2" />
                    <p class="text-slate-600 text-sm">Nenhum log registrado</p>
                    <p class="text-slate-700 text-xs mt-1">Logs aparecerão aqui conforme a aplicação trabalha</p>
                </div>
            @endforelse
        </div>
    </div>

    @endif {{-- end if $selectedApp --}}

</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" crossorigin="anonymous"></script>
@endassets

@script
<script>
(function () {
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
    const tickColor = isDark ? '#64748b' : '#94a3b8';

    const sharedOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600, easing: 'easeOutQuart' },
        elements: {
            line: { tension: 0.4, borderWidth: 2 },
            point: { radius: 0, hoverRadius: 6, hoverBorderWidth: 2 },
        },
        plugins: {
            tooltip: {
                backgroundColor: 'rgba(15,23,42,0.95)',
                borderColor: 'rgba(255,255,255,0.08)',
                borderWidth: 1,
                cornerRadius: 8,
                padding: 10,
                titleColor: '#f8fafc',
                bodyColor: '#94a3b8',
            },
        },
        scales: {
            x: {
                grid: { color: gridColor },
                ticks: { color: tickColor, maxTicksLimit: 8, font: { size: 11 } },
            },
            y: {
                grid: { color: gridColor },
                ticks: { color: tickColor, font: { size: 11 } },
                min: 0,
                beginAtZero: true,
            },
        },
    };

    const legendOpts = { display: true, position: 'bottom', labels: { color: tickColor, usePointStyle: true, padding: 16, font: { size: 11 } } };

    // Detect local peaks: points higher than both neighbours, plus the global max
    function getPeakRadii(data, peakRadius, maxRadius) {
        peakRadius = peakRadius || 4;
        maxRadius  = maxRadius  || 6;
        if (!data || data.length === 0) return [];

        const radii = new Array(data.length).fill(0);
        const bgColors = new Array(data.length).fill('transparent');

        // Find global max
        let globalMaxIdx = 0;
        let globalMaxVal = -Infinity;
        for (let i = 0; i < data.length; i++) {
            const v = data[i] ?? 0;
            if (v > globalMaxVal) { globalMaxVal = v; globalMaxIdx = i; }
        }

        // Mark local peaks (value > both neighbours and > 0)
        for (let i = 1; i < data.length - 1; i++) {
            const prev = data[i - 1] ?? 0;
            const curr = data[i] ?? 0;
            const next = data[i + 1] ?? 0;
            if (curr > 0 && curr > prev && curr > next) {
                radii[i] = peakRadius;
            }
        }

        // Global max gets bigger radius
        if (globalMaxVal > 0) {
            radii[globalMaxIdx] = maxRadius;
        }

        return radii;
    }

    function applyPeaks(dataset) {
        const radii = getPeakRadii(dataset.data, 4, 6);
        return {
            ...dataset,
            pointRadius: radii,
            pointHoverRadius: radii.map(r => r > 0 ? r + 2 : 6),
            pointBackgroundColor: radii.map((r, i) =>
                r >= 6 ? dataset.borderColor : (r > 0 ? dataset.borderColor + 'aa' : 'transparent')
            ),
            pointBorderColor: radii.map((r) =>
                r > 0 ? '#fff' : 'transparent'
            ),
            pointBorderWidth: radii.map(r => r >= 6 ? 2 : (r > 0 ? 1.5 : 0)),
        };
    }

    function initCharts() {
        // Read fresh data from JSON tags (Livewire updates these on re-render)
        const httpDataEl = document.getElementById('httpChartData');
        const rcDataEl   = document.getElementById('resourceChartData');
        const netDataEl  = document.getElementById('networkChartData');

        // ── HTTP Chart ──
        const httpContainer = document.getElementById('httpChartContainer');
        if (httpContainer && httpDataEl) {
            const canvas = httpContainer.querySelector('canvas');
            const existing = Chart.getChart(canvas);
            if (existing) existing.destroy();

            const data = JSON.parse(httpDataEl.textContent);
            if (data.labels && data.labels.length > 0) {
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [
                            { label: '2xx', data: data['2xx'] || [], borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', fill: true },
                            { label: '3xx', data: data['3xx'] || [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)', fill: true },
                            { label: '4xx', data: data['4xx'] || [], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', fill: true },
                            { label: '5xx', data: data['5xx'] || [], borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)', fill: true },
                        ].map(applyPeaks),
                    },
                    options: { ...sharedOptions, plugins: { ...sharedOptions.plugins, legend: legendOpts } },
                });
            }
        }

        // ── Resource Chart ──
        const rcContainer = document.getElementById('resourceChartContainer');
        if (rcContainer && rcDataEl) {
            const canvas = rcContainer.querySelector('canvas');
            const existing = Chart.getChart(canvas);
            if (existing) existing.destroy();

            const rcData = JSON.parse(rcDataEl.textContent);
            if (rcData.labels && rcData.labels.length > 0) {
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: rcData.labels,
                        datasets: (rcData.datasets || []).map(d => applyPeaks({
                            label: d.label,
                            data: d.data,
                            borderColor: d.borderColor,
                            backgroundColor: d.backgroundColor,
                            borderDash: d.borderDash || [],
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                        })),
                    },
                    options: {
                        ...sharedOptions,
                        plugins: { ...sharedOptions.plugins, legend: legendOpts },
                        scales: { ...sharedOptions.scales, y: { ...sharedOptions.scales.y, max: 100 } },
                    },
                });
            }
        }

        // ── Network Chart ──
        const netContainer = document.getElementById('networkChartContainer');
        if (netContainer && netDataEl) {
            const canvas = netContainer.querySelector('canvas');
            const existing = Chart.getChart(canvas);
            if (existing) existing.destroy();

            const netData = JSON.parse(netDataEl.textContent);
            if (netData.labels && netData.labels.length > 0) {
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: netData.labels,
                        datasets: (netData.datasets || []).map(d => applyPeaks({
                            label: d.label,
                            data: d.data,
                            borderColor: d.borderColor,
                            backgroundColor: d.backgroundColor,
                            borderDash: d.borderDash || [],
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                        })),
                    },
                    options: {
                        ...sharedOptions,
                        plugins: {
                            ...sharedOptions.plugins,
                            legend: legendOpts,
                            tooltip: {
                                ...sharedOptions.plugins.tooltip,
                                callbacks: {
                                    label: function(ctx) {
                                        return ctx.dataset.label + ': ' + (ctx.parsed.y ?? 0).toFixed(3) + ' MB';
                                    }
                                }
                            },
                        },
                        scales: {
                            ...sharedOptions.scales,
                            y: {
                                ...sharedOptions.scales.y,
                                ticks: {
                                    ...sharedOptions.scales.y.ticks,
                                    callback: function(v) { return v.toFixed(2) + ' MB'; }
                                }
                            },
                        },
                    },
                });
            }
        }

        // Auto-scroll logs
        const lc = document.getElementById('logsContainer');
        if (lc) lc.scrollTop = lc.scrollHeight;
    }

    // Initial render
    $nextTick(() => initCharts());

    // Reinitialize only when user changes app/period/container (NOT on poll)
    $wire.on('charts-need-update', () => {
        $nextTick(() => initCharts());
    });

    // Auto-scroll logs on poll updates
    Livewire.hook('morph.updated', () => {
        const lc = document.getElementById('logsContainer');
        if (lc) lc.scrollTop = lc.scrollHeight;
    });
})();
</script>
@endscript
</x-filament-panels::page>
