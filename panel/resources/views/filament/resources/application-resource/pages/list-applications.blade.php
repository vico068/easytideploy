<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <x-filament-panels::resources.tabs />

        {{-- Toggle de visualização --}}
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-400 dark:text-slate-500">
                @php $apps = $this->getApplications(); @endphp
                {{ $apps->count() }} {{ $apps->count() === 1 ? 'aplicação' : 'aplicações' }}
            </p>
            <div class="flex items-center gap-1 p-1 bg-white dark:bg-slate-900/60 rounded-xl border border-gray-100 dark:border-white/[0.06]">
                <button
                    wire:click="$set('viewMode', 'cards')"
                    @class([
                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                        'bg-brand-600 text-white shadow-sm shadow-brand-500/30' => $viewMode === 'cards',
                        'text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300' => $viewMode !== 'cards',
                    ])
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    Cards
                </button>
                <button
                    wire:click="$set('viewMode', 'table')"
                    @class([
                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                        'bg-brand-600 text-white shadow-sm shadow-brand-500/30' => $viewMode === 'table',
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

        {{-- CARDS VIEW --}}
        @if($viewMode === 'cards')
            @if($apps->isEmpty())
                <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.06] p-16 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-100 dark:bg-slate-800/50 flex items-center justify-center">
                        <x-heroicon-o-cube class="w-8 h-8 text-slate-400 dark:text-slate-500" />
                    </div>
                    <h3 class="text-base font-semibold text-slate-600 dark:text-slate-300">Nenhuma aplicação</h3>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Comece criando sua primeira aplicação para fazer deploy</p>
                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('create') }}"
                       class="inline-flex items-center gap-2 mt-5 px-4 py-2 rounded-xl
                              text-sm font-semibold text-white
                              bg-gradient-to-r from-brand-600 to-cyan-500
                              hover:from-brand-500 hover:to-cyan-400
                              transition-all duration-200 shadow-lg shadow-brand-500/25">
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
                                'active'     => 'bg-emerald-500',
                                'deploying'  => 'bg-amber-500 animate-pulse',
                                'failed'     => 'bg-red-500',
                                default      => 'bg-slate-400',
                            };
                            $borderGlow = match($statusValue) {
                                'active'    => 'dark:hover:border-emerald-500/30',
                                'deploying' => 'dark:hover:border-amber-500/30',
                                'failed'    => 'dark:hover:border-red-500/30',
                                default     => 'dark:hover:border-sky-500/30',
                            };
                            $stagger = 'stagger-' . min($index + 1, 6);
                            $lastDeploy = $app->latestDeployment;
                            $typeLabel = $app->type?->getLabel() ?? 'Desconhecido';
                            $typeIcon = $app->type?->getIcon() ?? 'heroicon-o-cube';
                        @endphp
                        <div class="card-premium {{ $stagger }} group relative
                                    bg-white dark:bg-slate-900/60 rounded-2xl
                                    border border-gray-100 dark:border-white/[0.06]
                                    hover:bg-gray-50/80 dark:hover:bg-slate-800/40
                                    hover:border-gray-200 {{ $borderGlow }}
                                    hover:shadow-xl hover:shadow-black/10
                                    backdrop-blur-sm overflow-hidden
                                    transition-all duration-300">

                            {{-- Glow decorativo --}}
                            <div class="absolute top-0 right-0 w-32 h-32 rounded-full blur-2xl -translate-y-8 translate-x-8
                                        pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-500
                                        {{ $statusValue === 'active' ? 'bg-emerald-500/10' : ($statusValue === 'failed' ? 'bg-red-500/10' : 'bg-brand-500/10') }}">
                            </div>

                            <div class="relative p-5">
                                {{-- Header: status + tipo --}}
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2.5 h-2.5 rounded-full {{ $statusDot }} flex-shrink-0 mt-0.5"></div>
                                        <div>
                                            <h3 class="font-bold text-gray-900 dark:text-white text-sm leading-tight
                                                       group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                                                {{ $app->name }}
                                            </h3>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">
                                                {{ $app->default_domain }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 px-2 py-0.5 rounded-lg
                                                bg-slate-50 dark:bg-slate-800/80
                                                border border-slate-100 dark:border-white/[0.05]">
                                        <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                            {{ $typeLabel }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Métricas da app --}}
                                <div class="grid grid-cols-3 gap-3 mb-4 p-3
                                            bg-slate-50/80 dark:bg-slate-800/40
                                            rounded-xl border border-slate-100 dark:border-white/[0.04]">
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
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">replicas</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">
                                            {{ $app->port }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">porta</div>
                                    </div>
                                </div>

                                {{-- Último deploy --}}
                                <div class="flex items-center gap-2 mb-4 text-xs text-slate-400 dark:text-slate-500">
                                    <x-heroicon-o-clock class="w-3.5 h-3.5 flex-shrink-0" />
                                    @if($lastDeploy)
                                        <span class="truncate">
                                            {{ $lastDeploy->git_commit_message ?? 'Deploy #' . substr($lastDeploy->id, 0, 8) }}
                                        </span>
                                        <span class="flex-shrink-0 text-slate-500 dark:text-slate-600">
                                            &middot; {{ $lastDeploy->created_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span>Nenhum deploy ainda</span>
                                    @endif
                                </div>

                                {{-- Ações --}}
                                <div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-white/[0.05]">
                                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('edit', ['record' => $app]) }}"
                                       class="flex-1 text-center py-1.5 rounded-lg text-xs font-semibold
                                              text-slate-500 dark:text-slate-400
                                              hover:bg-slate-100 dark:hover:bg-slate-800/60
                                              hover:text-brand-600 dark:hover:text-brand-400
                                              transition-all duration-150">
                                        Configurar
                                    </a>
                                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('logs', ['record' => $app]) }}"
                                       class="flex-1 text-center py-1.5 rounded-lg text-xs font-semibold
                                              text-slate-500 dark:text-slate-400
                                              hover:bg-slate-100 dark:hover:bg-slate-800/60
                                              hover:text-brand-600 dark:hover:text-brand-400
                                              transition-all duration-150">
                                        Logs
                                    </a>
                                    <a href="{{ route('filament.admin.pages.monitoring-dashboard', ['app' => $app->id]) }}"
                                       class="flex-1 text-center py-1.5 rounded-lg text-xs font-semibold
                                              text-slate-500 dark:text-slate-400
                                              hover:bg-slate-100 dark:hover:bg-slate-800/60
                                              hover:text-brand-600 dark:hover:text-brand-400
                                              transition-all duration-150">
                                        Monitor
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- TABLE VIEW --}}
        @if($viewMode === 'table')
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}
            {{ $this->table }}
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
        @endif
    </div>
</x-filament-panels::page>
