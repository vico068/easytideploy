<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period selector --}}
        <div class="flex items-center space-x-2">
            @foreach(['1h' => '1 hora', '6h' => '6 horas', '24h' => '24 horas', '7d' => '7 dias'] as $key => $label)
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

        {{-- Server status grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($servers as $server)
                <div @class([
                    'bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-5',
                    'border-green-200 dark:border-green-800' => $server->status->value === 'online',
                    'border-red-200 dark:border-red-800' => $server->status->value === 'offline',
                    'border-yellow-200 dark:border-yellow-800' => in_array($server->status->value, ['maintenance', 'draining']),
                ])>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $server->name }}</h3>
                        <span @class([
                            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' => $server->status->value === 'online',
                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' => $server->status->value === 'offline',
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' => in_array($server->status->value, ['maintenance', 'draining']),
                        ])>
                            {{ $server->status->getLabel() }}
                        </span>
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        {{ $server->ip_address }} &bull; {{ $server->containers->count() }} containers
                    </div>

                    {{-- CPU bar --}}
                    <div class="mb-2">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                            <span>CPU</span>
                            <span>{{ number_format($server->cpu_usage ?? 0, 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                                @class([
                                    'h-2 rounded-full transition-all duration-300',
                                    'bg-green-500' => ($server->cpu_usage ?? 0) <= 60,
                                    'bg-yellow-500' => ($server->cpu_usage ?? 0) > 60 && ($server->cpu_usage ?? 0) <= 80,
                                    'bg-red-500' => ($server->cpu_usage ?? 0) > 80,
                                ])
                                style="width: {{ min($server->cpu_usage ?? 0, 100) }}%"
                            ></div>
                        </div>
                    </div>

                    {{-- Memory bar --}}
                    <div class="mb-2">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                            <span>Memória</span>
                            <span>{{ number_format($server->memory_usage ?? 0, 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                                @class([
                                    'h-2 rounded-full transition-all duration-300',
                                    'bg-green-500' => ($server->memory_usage ?? 0) <= 60,
                                    'bg-yellow-500' => ($server->memory_usage ?? 0) > 60 && ($server->memory_usage ?? 0) <= 80,
                                    'bg-red-500' => ($server->memory_usage ?? 0) > 80,
                                ])
                                style="width: {{ min($server->memory_usage ?? 0, 100) }}%"
                            ></div>
                        </div>
                    </div>

                    {{-- Disk bar --}}
                    <div>
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                            <span>Disco</span>
                            <span>{{ number_format($server->disk_usage ?? 0, 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div
                                @class([
                                    'h-2 rounded-full transition-all duration-300',
                                    'bg-green-500' => ($server->disk_usage ?? 0) <= 60,
                                    'bg-yellow-500' => ($server->disk_usage ?? 0) > 60 && ($server->disk_usage ?? 0) <= 85,
                                    'bg-red-500' => ($server->disk_usage ?? 0) > 85,
                                ])
                                style="width: {{ min($server->disk_usage ?? 0, 100) }}%"
                            ></div>
                        </div>
                    </div>

                    @if($server->last_heartbeat)
                        <div class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                            Último heartbeat: {{ \Carbon\Carbon::parse($server->last_heartbeat)->diffForHumans() }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Recent deployments --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Deployments Recentes</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aplicação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quando</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($recentDeployments as $deployment)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $deployment->application->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">
                                {{ substr($deployment->commit_sha ?? '', 0, 7) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span @class([
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' => $deployment->status->value === 'running',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' => in_array($deployment->status->value, ['building', 'deploying', 'pending']),
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' => $deployment->status->value === 'failed',
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400' => in_array($deployment->status->value, ['cancelled', 'rolled_back']),
                                ])>
                                    {{ $deployment->status->getLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $deployment->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                Nenhum deployment recente
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
