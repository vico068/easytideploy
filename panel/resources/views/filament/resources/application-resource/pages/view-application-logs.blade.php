<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Log stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border dark:border-gray-700 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total (1h)</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total']) }}</div>
                    </div>
                    <x-heroicon-o-document-text class="w-8 h-8 text-gray-400 opacity-50" />
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-red-200 dark:border-red-900/50 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Erros</div>
                        <div class="text-2xl font-bold text-red-600">{{ $stats['errors'] }}</div>
                    </div>
                    <x-heroicon-o-x-circle class="w-8 h-8 text-red-500 opacity-50" />
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-yellow-200 dark:border-yellow-900/50 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Avisos</div>
                        <div class="text-2xl font-bold text-yellow-600">{{ $stats['warnings'] }}</div>
                    </div>
                    <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-yellow-500 opacity-50" />
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-red-300 dark:border-red-800 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">Críticos</div>
                        <div class="text-2xl font-bold text-red-800 dark:text-red-500">{{ $stats['criticals'] }}</div>
                    </div>
                    <x-heroicon-o-fire class="w-8 h-8 text-red-700 opacity-50" />
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="flex items-center justify-between flex-wrap gap-4 bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border dark:border-gray-700">
            <div class="flex items-center space-x-4 flex-wrap gap-2">
                <select
                    wire:model.live="selectedContainer"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-brand-500"
                >
                    <option value="">📦 Todos os containers</option>
                    @foreach($record->containers as $container)
                        <option value="{{ $container->id }}">
                            {{ $container->container_name }} ({{ $container->server->name ?? 'N/A' }})
                        </option>
                    @endforeach
                </select>

                <select
                    wire:model.live="logLevel"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-brand-500"
                >
                    <option value="">🎯 Todos os níveis</option>
                    <option value="debug">🔍 Debug</option>
                    <option value="info">ℹ️ Info</option>
                    <option value="warning">⚠️ Warning</option>
                    <option value="error">❌ Error</option>
                    <option value="critical">🔥 Critical</option>
                </select>

                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Buscar nos logs..."
                        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm pl-9 w-64 focus:ring-2 focus:ring-brand-500"
                    >
                    <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <label class="flex items-center space-x-2 cursor-pointer group">
                    <input
                        type="checkbox"
                        wire:model.live="autoRefresh"
                        class="rounded border-gray-300 dark:border-gray-700 text-brand-600 focus:ring-brand-500"
                    >
                    <span class="text-sm text-gray-600 dark:text-gray-400 group-hover:text-brand-600 transition-colors">
                        🔄 Auto-refresh (3s)
                    </span>
                </label>

                <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded-md">
                    {{ $logs->count() }} linhas
                </span>
            </div>
        </div>

        {{-- Log viewer usando component terminal-viewer --}}
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
