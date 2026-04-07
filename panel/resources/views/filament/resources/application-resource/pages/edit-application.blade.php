<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ═══════════════════════════════════════════════════════
             STATUS HEADER — App info + ações rápidas
        ═══════════════════════════════════════════════════════ --}}
        @php
            $app = $this->record;
            $statusVal = $app->status?->value ?? $app->status ?? 'stopped';
            $lastDeploy = $app->latestDeployment;

            $statusConfig = match($statusVal) {
                'active'    => ['dot' => 'bg-emerald-500', 'text' => 'Ativa', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'],
                'deploying' => ['dot' => 'bg-amber-500 animate-pulse', 'text' => 'Deployando', 'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'],
                'failed'    => ['dot' => 'bg-red-500', 'text' => 'Falhou', 'badge' => 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-400'],
                default     => ['dot' => 'bg-slate-400', 'text' => 'Parada', 'badge' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'],
            };
        @endphp

        <div class="relative overflow-hidden rounded-2xl
                    bg-white dark:bg-slate-900/60
                    border border-gray-100 dark:border-white/[0.06]
                    p-5">

            {{-- Glow sutil no fundo --}}
            <div class="absolute inset-0 bg-gradient-to-r from-brand-500/[0.03] to-transparent
                        dark:from-brand-500/[0.05] dark:to-transparent pointer-events-none rounded-2xl"></div>

            <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">

                {{-- Ícone + Info --}}
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <div class="flex-shrink-0 w-12 h-12 rounded-2xl
                                bg-gradient-to-br from-brand-500/10 to-cyan-500/10
                                dark:from-brand-500/20 dark:to-cyan-500/20
                                flex items-center justify-center
                                border border-brand-200/50 dark:border-brand-500/20">
                        <x-dynamic-component
                            :component="$app->type?->getIcon() ?? 'heroicon-o-cube'"
                            class="w-5 h-5 text-brand-600 dark:text-brand-400"
                        />
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                                {{ $app->name }}
                            </h2>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusConfig['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                                {{ $statusConfig['text'] }}
                            </span>
                        </div>

                        <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                            @if($app->primaryDomain?->domain)
                                <a href="{{ $app->primaryDomain->url }}" target="_blank"
                                   class="flex items-center gap-1 text-xs font-mono text-brand-600 dark:text-brand-400
                                          hover:underline transition-colors">
                                    <x-heroicon-m-globe-alt class="w-3.5 h-3.5" />
                                    {{ $app->primaryDomain->domain }}
                                    <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3 opacity-60" />
                                </a>
                            @else
                                <span class="text-xs font-mono text-slate-400 dark:text-slate-500">
                                    {{ $app->default_domain }}
                                </span>
                            @endif

                            @if($lastDeploy)
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                    <x-heroicon-m-clock class="w-3 h-3" />
                                    Deploy {{ $lastDeploy->created_at->diffForHumans() }}
                                </span>
                            @endif

                            <span class="text-[10px] text-slate-400 dark:text-slate-500">
                                {{ $app->type?->getLabel() ?? 'N/A' }}
                                · {{ $app->replicas }} réplica{{ $app->replicas !== 1 ? 's' : '' }}
                                · :{{ $app->port }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Links rápidos --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('logs', ['record' => $app]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold
                              text-slate-500 dark:text-slate-400
                              bg-slate-100 dark:bg-slate-800/60
                              border border-slate-200 dark:border-white/[0.06]
                              hover:bg-brand-50 dark:hover:bg-brand-500/10
                              hover:text-brand-600 dark:hover:text-brand-400
                              hover:border-brand-200 dark:hover:border-brand-500/30
                              transition-all duration-150">
                        <x-heroicon-m-document-text class="w-3.5 h-3.5" />
                        Logs
                    </a>
                    <a href="{{ route('filament.admin.pages.monitoring-dashboard', ['app' => $app->id]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold
                              text-slate-500 dark:text-slate-400
                              bg-slate-100 dark:bg-slate-800/60
                              border border-slate-200 dark:border-white/[0.06]
                              hover:bg-cyan-50 dark:hover:bg-cyan-500/10
                              hover:text-cyan-600 dark:hover:text-cyan-400
                              hover:border-cyan-200 dark:hover:border-cyan-500/30
                              transition-all duration-150">
                        <x-heroicon-m-chart-bar class="w-3.5 h-3.5" />
                        Monitor
                    </a>
                </div>
            </div>

        </div>

        {{-- ═══════════════════════════════════════════════════════
             FORM — Filament padrão
        ═══════════════════════════════════════════════════════ --}}
        <x-filament-panels::form wire:submit="save">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>

        {{-- Relation managers --}}
        <x-filament-panels::resources.relation-managers
            :active-locale="isset($activeLocale) ? $activeLocale : null"
            :active-manager="$this->activeRelationManager ?? null"
            :managers="$this->getRelationManagers()"
            :owner-record="$record"
            :page-class="static::class"
        />

    </div>

    {{-- Polling para manter status do header atualizado sem WebSocket --}}
    <div wire:poll.5000ms="refreshStatus" class="hidden"></div>
</x-filament-panels::page>
