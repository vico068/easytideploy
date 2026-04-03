<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period selector --}}
        <div class="flex items-center space-x-2">
            @foreach(['1h' => '1 hora', '6h' => '6 horas', '24h' => '24 horas', '7d' => '7 dias', '30d' => '30 dias'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    @class([
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        'bg-primary-600 text-white' => $period === $key,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $period !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Stats cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">CPU Médio</div>
                <div class="text-2xl font-bold text-primary-600">
                    {{ number_format($record->resourceUsages()->forPeriod($period)->avg('cpu_usage') ?? 0, 1) }}%
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Memória Média</div>
                <div class="text-2xl font-bold text-green-600">
                    {{ number_format($record->resourceUsages()->forPeriod($period)->avg('memory_usage') ?? 0, 1) }}%
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Network RX</div>
                <div class="text-2xl font-bold text-blue-600">
                    {{ \App\Models\ResourceUsage::formatBytes($record->resourceUsages()->forPeriod($period)->sum('network_rx') ?? 0) }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
                <div class="text-sm text-gray-500 dark:text-gray-400">Network TX</div>
                <div class="text-2xl font-bold text-purple-600">
                    {{ \App\Models\ResourceUsage::formatBytes($record->resourceUsages()->forPeriod($period)->sum('network_tx') ?? 0) }}
                </div>
            </div>
        </div>

        {{-- Charts placeholder --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
            <h3 class="text-lg font-medium mb-4">Uso de Recursos</h3>
            <div class="h-64 flex items-center justify-center text-gray-500">
                <p>Gráfico de métricas será renderizado aqui</p>
            </div>
        </div>

        {{-- Containers table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-medium">Containers</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Container</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Servidor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CPU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Memória</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($record->containers as $container)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-sm">
                                {{ $container->container_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $container->server->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                        <div
                                            class="h-2 rounded-full {{ $container->cpu_usage > 80 ? 'bg-red-500' : ($container->cpu_usage > 60 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                            style="width: {{ min($container->cpu_usage, 100) }}%"
                                        ></div>
                                    </div>
                                    <span>{{ number_format($container->cpu_usage, 1) }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                        <div
                                            class="h-2 rounded-full {{ $container->memory_usage > 80 ? 'bg-red-500' : ($container->memory_usage > 60 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                            style="width: {{ min($container->memory_usage, 100) }}%"
                                        ></div>
                                    </div>
                                    <span>{{ number_format($container->memory_usage, 1) }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span @class([
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    'bg-green-100 text-green-800' => $container->status->value === 'running',
                                    'bg-yellow-100 text-yellow-800' => in_array($container->status->value, ['starting', 'stopping']),
                                    'bg-red-100 text-red-800' => in_array($container->status->value, ['failed', 'unhealthy']),
                                    'bg-gray-100 text-gray-800' => $container->status->value === 'stopped',
                                ])>
                                    {{ $container->status->getLabel() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
