<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6" wire:poll.5000ms>
        <x-filament-panels::resources.tabs />

        {{-- Barra de controles: contagem + toggle de view --}}
        <div class="flex items-center justify-between gap-4">
            @php $apps = $this->getApplications(); @endphp

            {{-- Resumo de status --}}
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-sm text-slate-500 dark:text-slate-400 tabular-nums">
                    {{ $apps->count() }} {{ $apps->count() === 1 ? 'aplicação' : 'aplicações' }}
                </span>

                @php
                    $active   = $apps->filter(fn($a) => $a->status?->value === 'active')->count();
                    $deploying = $apps->filter(fn($a) => $a->status?->value === 'deploying')->count();
                    $failed   = $apps->filter(fn($a) => $a->status?->value === 'failed')->count();
                @endphp

                @if($active > 0)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold
                                 text-emerald-600 dark:text-emerald-400
                                 bg-emerald-50 dark:bg-emerald-500/10
                                 px-2.5 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        {{ $active }} ativa{{ $active !== 1 ? 's' : '' }}
                    </span>
                @endif
                @if($deploying > 0)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold
                                 text-amber-600 dark:text-amber-400
                                 bg-amber-50 dark:bg-amber-500/10
                                 px-2.5 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        {{ $deploying }} em deploy
                    </span>
                @endif
                @if($failed > 0)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold
                                 text-red-600 dark:text-red-400
                                 bg-red-50 dark:bg-red-500/10
                                 px-2.5 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        {{ $failed }} com falha
                    </span>
                @endif
            </div>

            {{-- Toggle Cards / Tabela --}}
            <div class="flex items-center gap-1 p-1
                        bg-white dark:bg-slate-900/60 rounded-xl
                        border border-gray-100 dark:border-white/[0.06]">
                <button
                    wire:click="$set('viewMode', 'cards')"
                    @class([
                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                        'bg-gradient-to-r from-brand-600 to-cyan-500 text-white shadow-sm' => $viewMode === 'cards',
                        'text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300' => $viewMode !== 'cards',
                    ])
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Cards
                </button>
                <button
                    wire:click="$set('viewMode', 'table')"
                    @class([
                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                        'bg-gradient-to-r from-brand-600 to-cyan-500 text-white shadow-sm' => $viewMode === 'table',
                        'text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300' => $viewMode !== 'table',
                    ])
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    Tabela
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════
             CARDS VIEW
        ═══════════════════════════════════════ --}}
        @if($viewMode === 'cards')

            @if($apps->isEmpty())
                {{-- Empty state --}}
                <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.06] p-16 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl
                                bg-gradient-to-br from-brand-50 to-cyan-50
                                dark:from-brand-500/10 dark:to-cyan-500/10
                                flex items-center justify-center">
                        <x-heroicon-o-cube class="w-8 h-8 text-brand-400" />
                    </div>
                    <h3 class="text-base font-bold text-slate-700 dark:text-slate-200">Nenhuma aplicação</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">
                        Comece criando sua primeira aplicação para fazer deploy
                    </p>
                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('create') }}"
                       class="inline-flex items-center gap-2 mt-6 px-5 py-2.5 rounded-xl
                              text-sm font-semibold text-white
                              bg-gradient-to-r from-brand-600 to-cyan-500
                              hover:from-brand-500 hover:to-cyan-400
                              hover:shadow-lg hover:shadow-brand-500/25
                              hover:scale-[1.02]
                              transition-all duration-200">
                        <x-heroicon-m-plus class="w-4 h-4" />
                        Criar aplicação
                    </a>
                </div>

            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($apps as $index => $app)
                        @php
                            $statusValue = $app->status?->value ?? $app->status;
                            $statusDot = match($statusValue) {
                                'active'    => 'bg-emerald-500',
                                'deploying' => 'bg-amber-500 animate-pulse',
                                'failed'    => 'bg-red-500',
                                default     => 'bg-slate-400',
                            };
                            $borderHover = match($statusValue) {
                                'active'    => 'dark:hover:border-emerald-500/30 hover:border-emerald-300/60',
                                'deploying' => 'dark:hover:border-amber-500/30 hover:border-amber-300/60',
                                'failed'    => 'dark:hover:border-red-500/30 hover:border-red-300/60',
                                default     => 'dark:hover:border-brand-500/30 hover:border-brand-300/60',
                            };
                            $glowColor = match($statusValue) {
                                'active'    => 'bg-emerald-500/10',
                                'deploying' => 'bg-amber-500/10',
                                'failed'    => 'bg-red-500/10',
                                default     => 'bg-brand-500/10',
                            };
                            $stagger = 'stagger-' . min($index + 1, 6);
                            $lastDeploy = $app->latestDeployment;
                            $typeLabel = $app->type?->getLabel() ?? 'N/A';
                            $typeIcon  = $app->type?->getIcon() ?? 'heroicon-o-cube';
                        @endphp

                        <div class="card-premium {{ $stagger }} group relative
                                    bg-white dark:bg-slate-900/60 rounded-2xl
                                    border border-gray-100 dark:border-white/[0.06]
                                    hover:bg-gray-50/80 dark:hover:bg-slate-800/40
                                    {{ $borderHover }}
                                    hover:shadow-xl hover:shadow-black/[0.08]
                                    backdrop-blur-sm overflow-hidden
                                    transition-all duration-300">

                            {{-- Glow decorativo no hover --}}
                            <div class="absolute top-0 right-0 w-32 h-32 rounded-full blur-3xl
                                        -translate-y-8 translate-x-8 pointer-events-none
                                        opacity-0 group-hover:opacity-100 transition-opacity duration-500
                                        {{ $glowColor }}"></div>

                            <div class="relative p-5">

                                {{-- Header: nome + tipo --}}
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        {{-- Ícone do tipo --}}
                                        <div class="flex-shrink-0 w-9 h-9 rounded-xl
                                                    bg-slate-100 dark:bg-slate-800/80
                                                    group-hover:bg-brand-50 dark:group-hover:bg-brand-500/10
                                                    flex items-center justify-center transition-colors">
                                            <x-dynamic-component
                                                :component="$typeIcon"
                                                class="w-4 h-4 text-slate-500 dark:text-slate-400
                                                       group-hover:text-brand-600 dark:group-hover:text-brand-400
                                                       transition-colors"
                                            />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-2 h-2 rounded-full {{ $statusDot }} flex-shrink-0"></div>
                                                <h3 class="font-bold text-gray-900 dark:text-white text-sm leading-tight truncate
                                                           group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                                                    {{ $app->name }}
                                                </h3>
                                            </div>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5 truncate">
                                                {{ $app->default_domain }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="flex-shrink-0 text-[10px] font-semibold uppercase tracking-wide
                                                 text-slate-400 dark:text-slate-500
                                                 bg-slate-50 dark:bg-slate-800/80
                                                 border border-slate-100 dark:border-white/[0.05]
                                                 px-2 py-0.5 rounded-lg ml-2">
                                        {{ $typeLabel }}
                                    </span>
                                </div>

                                {{-- Métricas --}}
                                <div class="grid grid-cols-3 gap-3 mb-4 p-3
                                            bg-slate-50/80 dark:bg-slate-800/40 rounded-xl
                                            border border-slate-100 dark:border-white/[0.04]">
                                    <div class="text-center">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">
                                            {{ $app->containers_count }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">containers</div>
                                    </div>
                                    <div class="text-center border-x border-slate-200 dark:border-white/[0.05]">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">
                                            {{ $app->replicas }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">réplicas</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">
                                            :{{ $app->port }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">porta</div>
                                    </div>
                                </div>

                                {{-- Último deploy --}}
                                <div class="flex items-center gap-2 mb-4 text-xs text-slate-400 dark:text-slate-500">
                                    <x-heroicon-o-clock class="w-3.5 h-3.5 flex-shrink-0" />
                                    @if($lastDeploy)
                                        <span class="truncate">
                                            {{ $lastDeploy->commit_message ?? 'Deploy #' . substr($lastDeploy->id, 0, 8) }}
                                        </span>
                                        <span class="flex-shrink-0 text-slate-300 dark:text-slate-600">&middot;</span>
                                        <span class="flex-shrink-0">{{ $lastDeploy->created_at->diffForHumans() }}</span>
                                    @else
                                        <span>Nenhum deploy ainda</span>
                                    @endif
                                </div>

                                {{-- Ações --}}
                                <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-white/[0.05]">
                                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('edit', ['record' => $app]) }}"
                                       class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-xl text-xs font-semibold
                                              text-slate-500 dark:text-slate-400
                                              hover:bg-brand-50 dark:hover:bg-brand-500/10
                                              hover:text-brand-600 dark:hover:text-brand-400
                                              transition-all duration-150">
                                        <x-heroicon-m-pencil-square class="w-3.5 h-3.5" />
                                        Editar
                                    </a>
                                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('logs', ['record' => $app]) }}"
                                       class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-xl text-xs font-semibold
                                              text-slate-500 dark:text-slate-400
                                              hover:bg-slate-100 dark:hover:bg-slate-800/60
                                              hover:text-slate-700 dark:hover:text-slate-200
                                              transition-all duration-150">
                                        <x-heroicon-m-document-text class="w-3.5 h-3.5" />
                                        Logs
                                    </a>
                                    <a href="{{ route('filament.admin.pages.monitoring-dashboard', ['app' => $app->id]) }}"
                                       class="flex-1 flex items-center justify-center gap-1 py-1.5 rounded-xl text-xs font-semibold
                                              text-slate-500 dark:text-slate-400
                                              hover:bg-slate-100 dark:hover:bg-slate-800/60
                                              hover:text-slate-700 dark:hover:text-slate-200
                                              transition-all duration-150">
                                        <x-heroicon-m-chart-bar class="w-3.5 h-3.5" />
                                        Monitor
                                    </a>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        @endif

        {{-- ═══════════════════════════════════════
             TABLE VIEW
        ═══════════════════════════════════════ --}}
        @if($viewMode === 'table')
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}
            {{ $this->table }}
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
        @endif

    </div>
</x-filament-panels::page>
