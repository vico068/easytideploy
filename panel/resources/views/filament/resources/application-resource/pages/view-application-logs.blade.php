<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Log stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white dark:bg-slate-900/60 p-5 border border-gray-100 dark:border-white/[0.06]
                        hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Total (1h)</div>
                        <div class="text-2xl font-bold font-display text-gray-900 dark:text-white">{{ number_format($stats['total']) }}</div>
                    </div>
                    <div class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800">
                        <x-heroicon-o-document-text class="w-5 h-5 text-slate-400" />
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white dark:bg-slate-900/60 p-5
                        border border-red-100 dark:border-red-500/20
                        hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Erros</div>
                        <div class="text-2xl font-bold font-display text-red-600 dark:text-red-400">{{ $stats['errors'] }}</div>
                    </div>
                    <div class="p-2 rounded-xl bg-red-50 dark:bg-red-500/10">
                        <x-heroicon-o-x-circle class="w-5 h-5 text-red-500" />
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white dark:bg-slate-900/60 p-5
                        border border-yellow-100 dark:border-amber-500/20
                        hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Avisos</div>
                        <div class="text-2xl font-bold font-display text-amber-600 dark:text-amber-400">{{ $stats['warnings'] }}</div>
                    </div>
                    <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-500/10">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500" />
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white dark:bg-slate-900/60 p-5
                        border border-red-200 dark:border-red-800/50
                        hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Críticos</div>
                        <div class="text-2xl font-bold font-display text-red-800 dark:text-red-400">{{ $stats['criticals'] }}</div>
                    </div>
                    <div class="p-2 rounded-xl bg-red-100 dark:bg-red-900/30">
                        <x-heroicon-o-fire class="w-5 h-5 text-red-700 dark:text-red-400" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="flex items-center justify-between flex-wrap gap-4
                    bg-white dark:bg-slate-900/60 rounded-2xl p-4
                    border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center flex-wrap gap-3">
                <select
                    wire:model.live="selectedContainer"
                    class="rounded-xl border-gray-200 dark:border-white/[0.08] dark:bg-slate-800 dark:text-slate-300
                           text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                >
                    <option value="">Todos os containers</option>
                    @foreach($record->containers as $container)
                        <option value="{{ $container->id }}">
                            {{ $container->container_name }} ({{ $container->server->name ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>

                <select
                    wire:model.live="logLevel"
                    class="rounded-xl border-gray-200 dark:border-white/[0.08] dark:bg-slate-800 dark:text-slate-300
                           text-sm focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                >
                    <option value="">Todos os níveis</option>
                    <option value="debug">Debug</option>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                    <option value="critical">Critical</option>
                </select>

                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Buscar nos logs..."
                        class="rounded-xl border-gray-200 dark:border-white/[0.08] dark:bg-slate-800 dark:text-slate-300
                               text-sm pl-9 w-64 focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                    >
                    <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input
                        type="checkbox"
                        wire:model.live="autoRefresh"
                        class="rounded border-gray-300 dark:border-slate-600 text-brand-600 focus:ring-brand-500"
                    >
                    <span class="text-sm text-slate-500 dark:text-slate-400 group-hover:text-brand-600 transition-colors">
                        Auto-refresh (3s)
                    </span>
                </label>

                <span class="text-xs text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-800/60 px-2.5 py-1 rounded-lg font-mono tabular-nums">
                    {{ $logs->count() }} linhas
                </span>
            </div>
        </div>

        {{-- Log viewer --}}
        <x-terminal-viewer
            :logs="$logs"
            :searchQuery="$searchQuery"
            :autoScroll="$autoRefresh"
            maxHeight="700px"
            emptyMessage="Nenhum log disponível"
            emptyIcon="heroicon-o-document-text"
            :autoRefresh="$autoRefresh ? 3000 : null"
        />
    </div>
</x-filament-panels::page>
