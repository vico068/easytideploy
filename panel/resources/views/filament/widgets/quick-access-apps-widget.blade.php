<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
            Acesso Rápido
        </h2>
        @if(!$apps->isEmpty())
            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $apps->count() }} apps</span>
        @endif
    </div>

    @if($apps->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/[0.06] p-8 text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800/50 flex items-center justify-center">
                <x-heroicon-o-cube class="w-6 h-6 text-slate-400 dark:text-slate-500" />
            </div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Nenhuma aplicação cadastrada</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Crie sua primeira app para começar a fazer deploy</p>
            <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('create') }}"
               class="inline-flex items-center gap-1.5 mt-4 px-3 py-1.5 rounded-lg
                      text-sm font-medium text-white
                      bg-gradient-to-r from-brand-600 to-cyan-500
                      hover:from-brand-500 hover:to-cyan-400
                      transition-all duration-200 shadow-lg shadow-brand-500/25">
                <x-heroicon-m-plus class="w-4 h-4" />
                Criar aplicação
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            @foreach($apps as $index => $app)
                @php
                    $statusValue = $app->status?->value ?? $app->status;
                    $statusDot = match($statusValue) {
                        'active'     => 'bg-emerald-500',
                        'deploying'  => 'bg-amber-500 animate-pulse',
                        'building'   => 'bg-amber-500 animate-building',
                        'failed'     => 'bg-red-500',
                        default      => 'bg-slate-400',
                    };
                    $borderGlow = match($statusValue) {
                        'active'     => 'dark:hover:border-emerald-500/30',
                        'deploying',
                        'building'   => 'dark:hover:border-amber-500/30',
                        'failed'     => 'dark:hover:border-red-500/30',
                        default      => 'dark:hover:border-sky-500/30',
                    };
                    $shadowGlow = match($statusValue) {
                        'active'     => 'hover:shadow-emerald-500/10',
                        'deploying',
                        'building'   => 'hover:shadow-amber-500/10',
                        'failed'     => 'hover:shadow-red-500/10',
                        default      => 'hover:shadow-sky-500/10',
                    };
                    $stagger = 'stagger-' . min($index + 1, 6);
                    $avgCpu = $app->containers->avg('cpu_usage') ?? 0;
                @endphp
                <div class="card-premium {{ $stagger }} group relative
                            bg-white dark:bg-slate-900/60 rounded-xl p-4
                            border border-gray-100 dark:border-white/[0.06]
                            hover:bg-gray-50/80 dark:hover:bg-slate-800/50
                            hover:border-gray-200 {{ $borderGlow }}
                            hover:shadow-md {{ $shadowGlow }}
                            backdrop-blur-sm overflow-hidden">

                    {{-- Glow decorativo no canto --}}
                    <div class="absolute top-0 right-0 w-16 h-16 rounded-full blur-xl -translate-y-4 translate-x-4
                                pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-300
                                {{ $statusValue === 'active' ? 'bg-emerald-500/15' : ($statusValue === 'failed' ? 'bg-red-500/15' : 'bg-sky-500/15') }}">
                    </div>

                    {{-- Status + containers --}}
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full {{ $statusDot }} flex-shrink-0"></div>
                            <span class="text-xs text-slate-500 dark:text-slate-500 tabular-nums">
                                {{ $app->containers_count }}c
                            </span>
                        </div>
                        @if($avgCpu > 0)
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-md tabular-nums
                                         {{ $avgCpu > 80
                                            ? 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400'
                                            : 'text-slate-500 dark:text-slate-500 bg-slate-50 dark:bg-slate-800/80' }}">
                                {{ round($avgCpu) }}%
                            </span>
                        @endif
                    </div>

                    {{-- App name --}}
                    <h3 class="font-semibold text-gray-900 dark:text-slate-100 text-sm truncate
                               group-hover:text-sky-600 dark:group-hover:text-brand-400 transition-colors duration-200">
                        {{ $app->name }}
                    </h3>
                    <p class="text-[10px] text-slate-400 dark:text-slate-600 truncate mt-0.5 font-mono">
                        {{ $app->slug }}
                    </p>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.05]">
                        <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('edit', ['record' => $app]) }}"
                           class="flex-1 text-center text-[10px] font-semibold text-slate-500 dark:text-slate-500
                                  hover:text-sky-600 dark:hover:text-brand-400 transition-colors uppercase tracking-wide">
                            Editar
                        </a>
                        <span class="text-slate-200 dark:text-slate-700">·</span>
                        <a href="{{ route('filament.admin.pages.monitoring-dashboard', ['app' => $app->id]) }}"
                           class="flex-1 text-center text-[10px] font-semibold text-slate-500 dark:text-slate-500
                                  hover:text-sky-600 dark:hover:text-brand-400 transition-colors uppercase tracking-wide">
                            Monitor
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
