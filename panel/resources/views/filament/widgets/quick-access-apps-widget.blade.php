{{-- Quick Access Apps Widget --}}
<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
            Acesso Rápido
        </h2>
        @if(!$apps->isEmpty())
            <span class="text-xs text-slate-400 dark:text-slate-500 tabular-nums">{{ $apps->count() }} apps</span>
        @endif
    </div>

    @if($apps->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-slate-700 p-8 text-center">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <x-heroicon-o-cube class="w-6 h-6 text-slate-400" />
            </div>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Nenhuma aplicação</p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Crie sua primeira app para fazer deploy</p>
            <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('create') }}"
               class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl
                      text-sm font-semibold text-white
                      bg-gradient-to-r from-sky-500 to-cyan-500
                      hover:from-sky-400 hover:to-cyan-400
                      transition-all duration-200 shadow-lg shadow-sky-500/25">
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
                    $hoverBorder = match($statusValue) {
                        'active'            => 'hover:border-emerald-400/60 dark:hover:border-emerald-500/40',
                        'deploying','building' => 'hover:border-amber-400/60 dark:hover:border-amber-500/40',
                        'failed'            => 'hover:border-red-400/60 dark:hover:border-red-500/40',
                        default             => 'hover:border-sky-400/60 dark:hover:border-sky-500/40',
                    };
                    $avgCpu = $app->containers->avg('cpu_usage') ?? 0;
                @endphp
                <div class="group relative overflow-hidden rounded-xl p-4
                            bg-white dark:bg-slate-800/70
                            border border-gray-100 dark:border-slate-700/60
                            {{ $hoverBorder }}
                            hover:shadow-md
                            transition-all duration-250">

                    {{-- Status dot + containers --}}
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $statusDot }}"></div>
                            <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                                {{ $app->containers_count }}c
                            </span>
                        </div>
                        @if($avgCpu > 0)
                            <span class="text-[10px] font-mono px-1 py-0.5 rounded-md tabular-nums
                                         {{ $avgCpu > 80
                                            ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'
                                            : 'bg-slate-50 text-slate-500 dark:bg-slate-700/60 dark:text-slate-400' }}">
                                {{ round($avgCpu) }}%
                            </span>
                        @endif
                    </div>

                    {{-- Nome --}}
                    <h3 class="font-semibold text-gray-900 dark:text-slate-100 text-sm truncate
                               group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                        {{ $app->name }}
                    </h3>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate mt-0.5 font-mono">
                        {{ $app->slug }}
                    </p>

                    {{-- Ações --}}
                    <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-gray-100 dark:border-slate-700/40">
                        <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('edit', ['record' => $app]) }}"
                           class="flex-1 text-center text-[10px] font-semibold uppercase tracking-wide
                                  text-slate-400 dark:text-slate-500
                                  hover:text-sky-600 dark:hover:text-sky-400 transition-colors py-0.5">
                            Editar
                        </a>
                        <span class="text-slate-200 dark:text-slate-600 text-xs">·</span>
                        <a href="{{ route('filament.admin.pages.monitoring-dashboard', ['app' => $app->id]) }}"
                           class="flex-1 text-center text-[10px] font-semibold uppercase tracking-wide
                                  text-slate-400 dark:text-slate-500
                                  hover:text-sky-600 dark:hover:text-sky-400 transition-colors py-0.5">
                            Monitor
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
