<div>
    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3">
        Acesso Rápido
    </h2>

    @if($apps->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-gray-200 dark:border-slate-700 p-8 text-center">
            <x-heroicon-o-cube class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-slate-600" />
            <p class="text-sm font-medium text-gray-500 dark:text-slate-400">Nenhuma aplicação cadastrada</p>
            <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('create') }}"
               class="inline-flex items-center gap-1.5 mt-3 text-sm text-sky-500 hover:text-sky-600 font-medium transition-colors">
                <x-heroicon-m-plus class="w-4 h-4" />
                Criar sua primeira aplicação
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            @foreach($apps as $app)
                @php
                    $statusColor = match($app->status?->value ?? $app->status) {
                        'active'    => 'bg-emerald-500',
                        'deploying' => 'bg-amber-500 animate-pulse',
                        'failed'    => 'bg-red-500',
                        default     => 'bg-slate-400',
                    };
                    $avgCpu = $app->containers->avg('cpu_usage') ?? 0;
                @endphp
                <div class="group bg-white dark:bg-slate-800/30 rounded-xl p-4 border border-gray-100 dark:border-white/5
                            hover:bg-gray-50 dark:hover:bg-slate-800/50 hover:border-sky-200 dark:hover:border-sky-500/30
                            hover:shadow-md hover:shadow-sky-500/10 transition-all duration-300">

                    {{-- Status + containers --}}
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full {{ $statusColor }}"></div>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $app->containers_count }} container{{ $app->containers_count !== 1 ? 's' : '' }}
                            </span>
                        </div>
                        @if($avgCpu > 0)
                            <span class="text-xs font-mono px-1.5 py-0.5 rounded-md
                                         {{ $avgCpu > 80 ? 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400' : 'text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50' }}">
                                {{ round($avgCpu) }}% CPU
                            </span>
                        @endif
                    </div>

                    {{-- App name --}}
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm truncate
                               group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors duration-200">
                        {{ $app->name }}
                    </h3>
                    <p class="text-xs text-slate-400 truncate mt-0.5">{{ $app->slug }}</p>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-white/5">
                        <a href="{{ \App\Filament\Resources\ApplicationResource::getUrl('edit', ['record' => $app]) }}"
                           class="flex-1 text-center text-xs font-medium text-slate-500 dark:text-slate-400
                                  hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                            Editar
                        </a>
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <a href="{{ route('filament.admin.pages.monitoring-dashboard', ['app' => $app->id]) }}"
                           class="flex-1 text-center text-xs font-medium text-slate-500 dark:text-slate-400
                                  hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                            Monitorar
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
