<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Log stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total (1h)</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total']) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Erros</div>
                <div class="text-2xl font-bold text-red-600">{{ $stats['errors'] }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Avisos</div>
                <div class="text-2xl font-bold text-yellow-600">{{ $stats['warnings'] }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Críticos</div>
                <div class="text-2xl font-bold text-red-800">{{ $stats['criticals'] }}</div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center space-x-4 flex-wrap gap-2">
                <select
                    wire:model.live="selectedContainer"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm"
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
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm"
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
                        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm pl-9 w-64"
                    >
                    <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model.live="autoRefresh"
                        class="rounded border-gray-300 dark:border-gray-700 text-primary-600"
                    >
                    <span class="text-sm text-gray-600 dark:text-gray-400">Auto-refresh</span>
                </label>

                <span class="text-xs text-gray-400">
                    {{ $logs->count() }} linhas
                </span>
            </div>
        </div>

        {{-- Log viewer --}}
        <div
            class="bg-gray-900 rounded-lg p-4 font-mono text-sm overflow-auto"
            style="max-height: 700px;"
            x-data="{ scrollToBottom: true }"
            x-init="$watch('$wire.autoRefresh', value => { if(value) scrollToBottom = true })"
            x-effect="if(scrollToBottom) $el.scrollTop = $el.scrollHeight"
            @if($autoRefresh) wire:poll.3s @endif
        >
            @forelse($logs as $log)
                <div class="flex py-0.5 hover:bg-gray-800 rounded px-2 group items-start">
                    <span class="text-gray-500 w-44 flex-shrink-0 select-none">
                        {{ $log->timestamp->format('Y-m-d H:i:s.v') }}
                    </span>
                    <span @class([
                        'w-20 flex-shrink-0 text-center',
                        'text-gray-500' => $log->level->value === 'debug',
                        'text-blue-400' => $log->level->value === 'info',
                        'text-yellow-400' => $log->level->value === 'warning',
                        'text-red-400' => $log->level->value === 'error',
                        'text-red-600 font-bold' => $log->level->value === 'critical',
                    ])>
                        [{{ strtoupper($log->level->value) }}]
                    </span>
                    @if($log->container)
                        <span class="text-cyan-600 w-24 flex-shrink-0 text-xs truncate" title="{{ $log->container_id }}">
                            [{{ $log->container->short_container_id ?? substr($log->container_id, 0, 8) }}]
                        </span>
                    @endif
                    <span class="text-gray-200 flex-1 break-all">
                        @if($searchQuery)
                            {!! str_replace(
                                e($searchQuery),
                                '<mark class="bg-yellow-500/30 text-yellow-200 rounded px-0.5">'.e($searchQuery).'</mark>',
                                e($log->message)
                            ) !!}
                        @else
                            {{ $log->message }}
                        @endif
                    </span>
                </div>
            @empty
                <div class="text-center text-gray-500 py-12">
                    <x-heroicon-o-document-text class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>Nenhum log disponível</p>
                    <p class="text-xs mt-1">Os logs aparecerão aqui quando a aplicação estiver em execução</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
