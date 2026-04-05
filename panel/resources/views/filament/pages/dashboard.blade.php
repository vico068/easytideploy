<x-filament-panels::page>
<div class="space-y-6" x-data="dashboardPage()">

    {{-- ═══════════════════════════════════════════════════════════════
         HERO — Saudação + Ações rápidas
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-2xl
                bg-gradient-to-br from-brand-600 via-brand-700 to-cyan-600
                dark:from-brand-900/80 dark:via-navy-900 dark:to-cyan-950
                p-6 md:p-8">

        {{-- Decoração de fundo --}}
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full
                        bg-white/5 blur-3xl"></div>
            <div class="absolute -bottom-12 -left-12 w-48 h-48 rounded-full
                        bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute top-1/2 right-1/4 w-32 h-32 rounded-full
                        bg-white/3 blur-2xl"></div>
        </div>

        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <p class="text-brand-200 dark:text-brand-300 text-sm font-medium mb-1">
                    {{ $greeting }},
                </p>
                <h1 class="text-2xl md:text-3xl font-bold text-white tracking-tight">
                    {{ auth()->user()->name }}
                </h1>
                <p class="text-brand-200/70 dark:text-brand-300/60 text-sm mt-1.5 flex items-center gap-2">
                    <x-heroicon-m-calendar-days class="w-4 h-4" />
                    {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
                    <span class="opacity-50">·</span>
                    <span class="font-mono tabular-nums">{{ now()->format('H:i') }}</span>
                </p>

                {{-- Pills de status --}}
                <div class="flex flex-wrap items-center gap-2 mt-4">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full
                                 bg-white/10 backdrop-blur-sm text-white/80 text-xs font-medium">
                        <div class="w-1.5 h-1.5 rounded-full
                                    {{ $activeApps > 0 ? 'bg-emerald-400 animate-pulse' : 'bg-slate-400' }}"></div>
                        {{ $activeApps }} app{{ $activeApps !== 1 ? 's' : '' }} ativa{{ $activeApps !== 1 ? 's' : '' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full
                                 bg-white/10 backdrop-blur-sm text-white/80 text-xs font-medium">
                        <div class="w-1.5 h-1.5 rounded-full
                                    {{ $runningContainers > 0 ? 'bg-cyan-400' : 'bg-slate-400' }}"></div>
                        {{ $runningContainers }} container{{ $runningContainers !== 1 ? 's' : '' }}
                    </span>
                    @if($failedDeploys > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full
                                     bg-red-500/20 backdrop-blur-sm text-red-200 text-xs font-medium">
                            <x-heroicon-m-exclamation-triangle class="w-3 h-3" />
                            {{ $failedDeploys }} falha{{ $failedDeploys !== 1 ? 's' : '' }} (7d)
                        </span>
                    @endif
                </div>
            </div>

            {{-- Ações rápidas --}}
            <div class="flex flex-wrap gap-3">
                <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl
                          bg-white text-brand-700 font-semibold text-sm
                          hover:bg-brand-50
                          hover:shadow-lg hover:shadow-white/20
                          hover:scale-[1.02]
                          transition-all duration-200">
                    <x-heroicon-m-plus class="w-4 h-4" />
                    Nova Aplicação
                </a>
                <a href="{{ \App\Filament\Resources\DeploymentResource::getUrl('index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl
                          bg-white/10 backdrop-blur-sm text-white font-semibold text-sm
                          border border-white/20
                          hover:bg-white/20
                          hover:scale-[1.02]
                          transition-all duration-200">
                    <x-heroicon-m-rocket-launch class="w-4 h-4" />
                    Deployments
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         STATS ROW
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">

        {{-- Aplicações --}}
        <div class="group relative overflow-hidden rounded-2xl
                    bg-white dark:bg-slate-900/60 p-5
                    border border-gray-100 dark:border-white/[0.06]
                    hover:border-sky-200 dark:hover:border-brand-500/30
                    hover:shadow-lg hover:shadow-brand-500/10
                    transition-all duration-300 animate-fade-in-up stagger-1">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-500/0 to-cyan-500/0
                        group-hover:from-brand-500/5 group-hover:to-cyan-500/3
                        transition-all duration-500 rounded-2xl pointer-events-none"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                        Aplicações
                    </span>
                    <div class="p-2 rounded-xl bg-brand-50 dark:bg-brand-500/15 ring-1 ring-brand-200/50 dark:ring-brand-500/20">
                        <x-heroicon-o-cube class="w-4 h-4 text-brand-600 dark:text-brand-400" />
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $totalApps }}</div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                    <span class="text-emerald-500 font-semibold">{{ $activeApps }}</span> ativas
                </p>
            </div>
        </div>

        {{-- Containers --}}
        <div class="group relative overflow-hidden rounded-2xl
                    bg-white dark:bg-slate-900/60 p-5
                    border border-gray-100 dark:border-white/[0.06]
                    hover:border-cyan-200 dark:hover:border-cyan-500/30
                    hover:shadow-lg hover:shadow-cyan-500/10
                    transition-all duration-300 animate-fade-in-up stagger-2">
            <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/0 to-brand-500/0
                        group-hover:from-cyan-500/5 group-hover:to-brand-500/3
                        transition-all duration-500 rounded-2xl pointer-events-none"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                        Containers
                    </span>
                    <div class="p-2 rounded-xl bg-cyan-50 dark:bg-cyan-500/15 ring-1 ring-cyan-200/50 dark:ring-cyan-500/20">
                        <x-heroicon-o-server-stack class="w-4 h-4 text-cyan-600 dark:text-cyan-400" />
                    </div>
                </div>
                <div class="flex items-end justify-between">
                    <div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $runningContainers }}</div>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                            <span class="text-emerald-500 font-semibold">{{ $healthyContainers }}</span> saudáveis
                        </p>
                    </div>
                    @if($runningContainers > 0)
                        <div class="flex items-center gap-1.5 pb-1">
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span class="text-[10px] font-semibold text-emerald-500">live</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Deploys ativos --}}
        <div class="group relative overflow-hidden rounded-2xl
                    bg-white dark:bg-slate-900/60 p-5
                    border border-gray-100 dark:border-white/[0.06]
                    hover:border-emerald-200 dark:hover:border-emerald-500/30
                    hover:shadow-lg hover:shadow-emerald-500/10
                    transition-all duration-300 animate-fade-in-up stagger-3">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/0 to-green-500/0
                        group-hover:from-emerald-500/5 group-hover:to-green-500/3
                        transition-all duration-500 rounded-2xl pointer-events-none"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                        Running
                    </span>
                    <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 ring-1 ring-emerald-200/50 dark:ring-emerald-500/20">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $totalDeploys }}</div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">deploys ativos</p>
            </div>
        </div>

        {{-- Hoje --}}
        <div class="group relative overflow-hidden rounded-2xl
                    bg-white dark:bg-slate-900/60 p-5
                    border border-gray-100 dark:border-white/[0.06]
                    hover:border-amber-200 dark:hover:border-amber-500/30
                    hover:shadow-lg hover:shadow-amber-500/10
                    transition-all duration-300 animate-fade-in-up stagger-4">
            <div class="absolute inset-0 bg-gradient-to-br from-amber-500/0 to-orange-500/0
                        group-hover:from-amber-500/5 group-hover:to-orange-500/3
                        transition-all duration-500 rounded-2xl pointer-events-none"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                        Hoje
                    </span>
                    <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-500/15 ring-1 ring-amber-200/50 dark:ring-amber-500/20">
                        <x-heroicon-o-rocket-launch class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $todayDeploys }}</div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">deploys hoje</p>
            </div>
        </div>

        {{-- Falhas --}}
        <div class="group relative overflow-hidden rounded-2xl
                    p-5 transition-all duration-300 animate-fade-in-up stagger-5
                    {{ $failedDeploys > 0
                        ? 'bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-500/30 hover:shadow-lg hover:shadow-red-500/10'
                        : 'bg-white dark:bg-slate-900/60 border border-gray-100 dark:border-white/[0.06] hover:border-green-200 dark:hover:border-emerald-500/30 hover:shadow-lg hover:shadow-emerald-500/10' }}">
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[10px] font-bold uppercase tracking-widest
                                 {{ $failedDeploys > 0 ? 'text-red-400' : 'text-slate-400 dark:text-slate-500' }}">
                        Falhas (7d)
                    </span>
                    <div class="p-2 rounded-xl
                                {{ $failedDeploys > 0
                                    ? 'bg-red-100 dark:bg-red-500/15 ring-1 ring-red-200 dark:ring-red-500/20'
                                    : 'bg-emerald-50 dark:bg-emerald-500/15 ring-1 ring-emerald-200/50 dark:ring-emerald-500/20' }}">
                        @if($failedDeploys > 0)
                            <x-heroicon-o-x-circle class="w-4 h-4 text-red-600 dark:text-red-400" />
                        @else
                            <x-heroicon-o-shield-check class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        @endif
                    </div>
                </div>
                <div class="text-3xl font-bold tracking-tight
                            {{ $failedDeploys > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                    {{ $failedDeploys }}
                </div>
                <p class="text-xs mt-1
                          {{ $failedDeploys > 0 ? 'text-red-500/80' : 'text-slate-400 dark:text-slate-500' }}">
                    {{ $failedDeploys > 0 ? 'verificar logs' : 'sem falhas' }}
                </p>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         CONTEÚDO PRINCIPAL — 2 colunas
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        {{-- COLUNA ESQUERDA (7/12) ──────────────────────────────────── --}}
        <div class="xl:col-span-7 space-y-6">

            {{-- Gráfico: Atividade de Deployments ──────────────── --}}
            <div class="bg-white dark:bg-slate-900/60 rounded-2xl
                        border border-gray-100 dark:border-white/[0.06] p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                            Atividade de Deployments
                        </h2>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                            Últimos 14 dias
                        </p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-slate-400 dark:text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-brand-500"></span>
                            Sucesso
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-red-500"></span>
                            Falha
                        </span>
                    </div>
                </div>
                <div class="relative" style="height: 180px;">
                    <canvas id="deployActivityChart"></canvas>
                </div>
            </div>

            {{-- Timeline: Deployments Recentes ──────────────────── --}}
            <div class="bg-white dark:bg-slate-900/60 rounded-2xl
                        border border-gray-100 dark:border-white/[0.06]">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05] flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                        Deployments Recentes
                    </h2>
                    <a href="{{ \App\Filament\Resources\DeploymentResource::getUrl('index') }}"
                       class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                        Ver todos →
                    </a>
                </div>

                @if($recentDeployments->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <x-heroicon-o-rocket-launch class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Nenhum deploy realizado ainda</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Crie uma aplicação e faça seu primeiro deploy</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                        @foreach($recentDeployments as $index => $deploy)
                            @php
                                $statusVal = $deploy->status?->value ?? $deploy->status ?? 'unknown';
                                $dotColor = match($statusVal) {
                                    'running'   => 'bg-emerald-500',
                                    'building'  => 'bg-amber-500 animate-pulse',
                                    'queued'    => 'bg-brand-500 animate-pulse',
                                    'failed'    => 'bg-red-500',
                                    default     => 'bg-slate-400',
                                };
                                $badgeColor = match($statusVal) {
                                    'running'  => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
                                    'building' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
                                    'queued'   => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                                    'failed'   => 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-400',
                                    default    => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                                };
                                $statusLabel = match($statusVal) {
                                    'running'  => 'Rodando',
                                    'building' => 'Building',
                                    'queued'   => 'Na fila',
                                    'failed'   => 'Falhou',
                                    'stopped'  => 'Parado',
                                    default    => ucfirst($statusVal),
                                };
                            @endphp
                            <div class="px-6 py-3.5 flex items-center gap-4 hover:bg-slate-50/60 dark:hover:bg-white/[0.02] transition-colors">
                                {{-- Dot --}}
                                <div class="flex-shrink-0 w-2 h-2 rounded-full {{ $dotColor }}"></div>

                                {{-- App + commit --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $deploy->application?->name ?? 'Aplicação removida' }}
                                        </span>
                                        @if($deploy->short_commit_sha)
                                            <span class="text-xs font-mono text-slate-400 dark:text-slate-500 flex-shrink-0
                                                         bg-slate-100 dark:bg-slate-800/60 px-1.5 py-0.5 rounded-md">
                                                {{ $deploy->short_commit_sha }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($deploy->commit_message)
                                        <p class="text-xs text-slate-400 dark:text-slate-500 truncate mt-0.5">
                                            {{ $deploy->commit_message }}
                                        </p>
                                    @endif
                                </div>

                                {{-- Badge + tempo --}}
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $badgeColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                    <span class="text-xs text-slate-400 dark:text-slate-500 tabular-nums hidden sm:block">
                                        {{ $deploy->created_at?->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- COLUNA DIREITA (5/12) ──────────────────────────────────── --}}
        <div class="xl:col-span-5 space-y-6">

            {{-- Apps Rápidas ─────────────────────────────────────── --}}
            <div class="bg-white dark:bg-slate-900/60 rounded-2xl
                        border border-gray-100 dark:border-white/[0.06]">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05] flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                        Suas Aplicações
                    </h2>
                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('index') }}"
                       class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                        Ver todas →
                    </a>
                </div>

                @if($recentApps->isEmpty())
                    <div class="px-6 py-10 text-center">
                        <x-heroicon-o-cube class="w-9 h-9 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Nenhuma aplicação ainda</p>
                        <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('create') }}"
                           class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl
                                  text-sm font-semibold text-white
                                  bg-gradient-to-r from-brand-600 to-cyan-500
                                  hover:from-brand-500 hover:to-cyan-400
                                  transition-all duration-200 shadow-lg shadow-brand-500/25">
                            <x-heroicon-m-plus class="w-4 h-4" />
                            Criar aplicação
                        </a>
                    </div>
                @else
                    <div class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                        @foreach($recentApps as $app)
                            @php
                                $statusVal = $app->status?->value ?? $app->status;
                                $dot = match($statusVal) {
                                    'active'    => 'bg-emerald-500',
                                    'deploying' => 'bg-amber-500 animate-pulse',
                                    'building'  => 'bg-amber-500 animate-building',
                                    'failed'    => 'bg-red-500',
                                    default     => 'bg-slate-400',
                                };
                                $avgCpu = $app->containers->avg('cpu_usage') ?? 0;
                            @endphp
                            <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('edit', ['record' => $app]) }}"
                               class="flex items-center gap-4 px-6 py-4
                                      hover:bg-slate-50/60 dark:hover:bg-white/[0.02]
                                      transition-colors group">
                                {{-- Ícone do tipo --}}
                                <div class="flex-shrink-0 w-9 h-9 rounded-xl
                                            bg-slate-100 dark:bg-slate-800/60
                                            flex items-center justify-center
                                            group-hover:bg-brand-50 dark:group-hover:bg-brand-500/10
                                            transition-colors">
                                    <x-dynamic-component
                                        :component="$app->type?->getIcon() ?? 'heroicon-o-cube'"
                                        class="w-4 h-4 text-slate-500 dark:text-slate-400
                                               group-hover:text-brand-600 dark:group-hover:text-brand-400
                                               transition-colors"
                                    />
                                </div>

                                {{-- Nome + domínio --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $dot }}"></div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate
                                                     group-hover:text-brand-600 dark:group-hover:text-brand-400
                                                     transition-colors">
                                            {{ $app->name }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] font-mono text-slate-400 dark:text-slate-500 truncate mt-0.5">
                                        {{ $app->slug }}
                                    </p>
                                </div>

                                {{-- CPU + containers --}}
                                <div class="flex items-center gap-3 flex-shrink-0 text-right">
                                    @if($avgCpu > 0)
                                        <span class="text-xs font-mono tabular-nums px-1.5 py-0.5 rounded-md
                                                     {{ $avgCpu > 80
                                                         ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'
                                                         : 'bg-slate-100 text-slate-500 dark:bg-slate-800/60 dark:text-slate-400' }}">
                                            {{ round($avgCpu) }}%
                                        </span>
                                    @endif
                                    <span class="text-xs text-slate-400 dark:text-slate-500 tabular-nums">
                                        {{ $app->containers_count }}c
                                    </span>
                                    <x-heroicon-m-chevron-right class="w-4 h-4 text-slate-300 dark:text-slate-600
                                                                        group-hover:text-brand-500 transition-colors" />
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- CTA se tiver mais apps --}}
                    <div class="px-6 py-3 border-t border-gray-50 dark:border-white/[0.04]">
                        <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('index') }}"
                           class="flex items-center justify-center gap-1.5 w-full py-2 rounded-xl text-xs font-semibold
                                  text-slate-400 dark:text-slate-500
                                  hover:bg-slate-100 dark:hover:bg-slate-800/60
                                  hover:text-brand-600 dark:hover:text-brand-400
                                  transition-all duration-150">
                            Ver todas as aplicações
                            <x-heroicon-m-arrow-right class="w-3.5 h-3.5" />
                        </a>
                    </div>
                @endif
            </div>

            {{-- Saúde dos Servidores ──────────────────────────────── --}}
            <div class="bg-white dark:bg-slate-900/60 rounded-2xl
                        border border-gray-100 dark:border-white/[0.06]">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05] flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                        Infraestrutura
                    </h2>
                    <a href="{{ \App\Filament\Resources\ServerResource::getUrl('index') }}"
                       class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                        Gerenciar →
                    </a>
                </div>

                @if($servers->isEmpty())
                    <div class="px-6 py-10 text-center">
                        <x-heroicon-o-server class="w-9 h-9 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Nenhum servidor registrado</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-50 dark:divide-white/[0.04]">
                        @foreach($servers as $server)
                            @php
                                $sStatus = $server->status ?? 'offline';
                                $sDot = match($sStatus) {
                                    'online'  => 'bg-emerald-500',
                                    'warning' => 'bg-amber-500 animate-pulse',
                                    default   => 'bg-red-500',
                                };
                                $cpuPct = min((int)($server->cpu_usage ?? 0), 100);
                                $memPct = min((int)($server->memory_usage ?? 0), 100);
                                $barCpu = $cpuPct > 80 ? 'bg-red-500' : ($cpuPct > 60 ? 'bg-amber-500' : 'bg-brand-500');
                                $barMem = $memPct > 80 ? 'bg-red-500' : ($memPct > 60 ? 'bg-amber-500' : 'bg-cyan-500');
                            @endphp
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-2 h-2 rounded-full {{ $sDot }}"></div>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $server->name }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">
                                        {{ $server->ip_address }}
                                    </span>
                                </div>

                                {{-- Barras CPU / Memória --}}
                                <div class="space-y-2">
                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 w-7">CPU</span>
                                        <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-700 {{ $barCpu }}"
                                                 style="width: {{ $cpuPct }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-mono tabular-nums text-slate-400 dark:text-slate-500 w-8 text-right">
                                            {{ $cpuPct }}%
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 w-7">RAM</span>
                                        <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-700 {{ $barMem }}"
                                                 style="width: {{ $memPct }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-mono tabular-nums text-slate-400 dark:text-slate-500 w-8 text-right">
                                            {{ $memPct }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Links rápidos ────────────────────────────────────── --}}
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ \App\Filament\Resources\DomainResource::getUrl('index') }}"
                   class="flex items-center gap-3 p-4 rounded-2xl
                          bg-white dark:bg-slate-900/60
                          border border-gray-100 dark:border-white/[0.06]
                          hover:border-brand-200 dark:hover:border-brand-500/30
                          hover:shadow-md hover:shadow-brand-500/10
                          hover:-translate-y-0.5
                          transition-all duration-200 group">
                    <div class="p-2 rounded-xl bg-brand-50 dark:bg-brand-500/15">
                        <x-heroicon-o-globe-alt class="w-4 h-4 text-brand-600 dark:text-brand-400" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-900 dark:text-white">Domínios</p>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500">SSL & DNS</p>
                    </div>
                </a>

                <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}"
                   class="flex items-center gap-3 p-4 rounded-2xl
                          bg-white dark:bg-slate-900/60
                          border border-gray-100 dark:border-white/[0.06]
                          hover:border-cyan-200 dark:hover:border-cyan-500/30
                          hover:shadow-md hover:shadow-cyan-500/10
                          hover:-translate-y-0.5
                          transition-all duration-200 group">
                    <div class="p-2 rounded-xl bg-cyan-50 dark:bg-cyan-500/15">
                        <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-900 dark:text-white">Auditoria</p>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500">Activity Log</p>
                    </div>
                </a>

                <a href="{{ route('filament.admin.pages.monitoring-dashboard') }}"
                   class="flex items-center gap-3 p-4 rounded-2xl
                          bg-white dark:bg-slate-900/60
                          border border-gray-100 dark:border-white/[0.06]
                          hover:border-emerald-200 dark:hover:border-emerald-500/30
                          hover:shadow-md hover:shadow-emerald-500/10
                          hover:-translate-y-0.5
                          transition-all duration-200 group">
                    <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/15">
                        <x-heroicon-o-chart-bar-square class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-900 dark:text-white">Monitoring</p>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500">Métricas live</p>
                    </div>
                </a>

                <a href="{{ \App\Filament\Resources\ServerResource::getUrl('create') }}"
                   class="flex items-center gap-3 p-4 rounded-2xl
                          bg-white dark:bg-slate-900/60
                          border border-gray-100 dark:border-white/[0.06]
                          hover:border-amber-200 dark:hover:border-amber-500/30
                          hover:shadow-md hover:shadow-amber-500/10
                          hover:-translate-y-0.5
                          transition-all duration-200 group">
                    <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-500/15">
                        <x-heroicon-o-plus-circle class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-900 dark:text-white">Servidor</p>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500">Adicionar novo</p>
                    </div>
                </a>
            </div>

        </div>

    </div>

</div>

{{-- Chart.js para Deploy Activity ──────────────────────────────────────── --}}
@push('scripts')
<script>
function dashboardPage() {
    return {
        init() {
            this.$nextTick(() => this.initChart());
        },
        initChart() {
            const canvas = document.getElementById('deployActivityChart');
            if (!canvas) return;

            const isDark = document.documentElement.classList.contains('dark');
            const gridColor  = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
            const labelColor = isDark ? '#64748b' : '#94a3b8';

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: @json($activityLabels),
                    datasets: [
                        {
                            label: 'Sucesso',
                            data: @json($activitySuccess),
                            backgroundColor: isDark ? 'rgba(13,139,250,0.75)' : 'rgba(13,139,250,0.6)',
                            borderRadius: 6,
                            borderSkipped: false,
                        },
                        {
                            label: 'Falha',
                            data: @json($activityFailed),
                            backgroundColor: isDark ? 'rgba(239,68,68,0.65)' : 'rgba(239,68,68,0.5)',
                            borderRadius: 6,
                            borderSkipped: false,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 750, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? 'rgba(15,23,42,0.95)' : 'rgba(255,255,255,0.97)',
                            titleColor: isDark ? '#f1f5f9' : '#0f172a',
                            bodyColor:  isDark ? '#94a3b8' : '#64748b',
                            borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)',
                            borderWidth: 1,
                            cornerRadius: 10,
                            padding: 10,
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: labelColor, font: { size: 10, family: 'Manrope' } },
                            border: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: labelColor,
                                font: { size: 10, family: 'Manrope' },
                                stepSize: 1,
                                precision: 0,
                            },
                            grid: { color: gridColor },
                            border: { display: false },
                        },
                    },
                },
            });
        },
    };
}
</script>
@endpush
</x-filament-panels::page>
