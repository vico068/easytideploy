<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Period selector --}}
        <div class="flex items-center space-x-2 bg-white dark:bg-gray-800 rounded-xl p-2 shadow-sm border dark:border-gray-700 inline-flex">
            @foreach(['1h' => '1 hora', '6h' => '6 horas', '24h' => '24 horas', '7d' => '7 dias'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    @class([
                        'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200',
                        'bg-gradient-to-r from-brand-600 to-cyan-500 text-white shadow-md' => $period === $key,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700/50 dark:text-gray-300 dark:hover:bg-gray-700' => $period !== $key,
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
                    'bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 p-6 hover:shadow-lg transition-all duration-200',
                    'border-emerald-300 dark:border-emerald-700 hover:border-emerald-400' => $server->status->value === 'online',
                    'border-red-300 dark:border-red-700 hover:border-red-400' => $server->status->value === 'offline',
                    'border-yellow-300 dark:border-yellow-700 hover:border-yellow-400' => in_array($server->status->value, ['maintenance', 'draining']),
                ])>
                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-server class="w-5 h-5 text-gray-500" />
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $server->name }}</h3>
                        </div>
                        <x-status-badge :status="$server->status" size="sm" />
                    </div>

                    {{-- Info --}}
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-4 space-y-1">
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-m-globe-alt class="w-4 h-4" />
                            <span class="font-mono">{{ $server->ip_address }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-m-cube class="w-4 h-4" />
                            <span>{{ $server->containers->count() }} containers ativos</span>
                        </div>
                    </div>

                    {{-- CPU Progress Bar --}}
                    <div class="mb-3">
                        <x-progress-bar
                            :value="$server->cpu_usage ?? 0"
                            label="CPU"
                            :showValue="true"
                            variant="auto"
                            height="h-2.5"
                        />
                    </div>

                    {{-- Memory Progress Bar --}}
                    <div class="mb-3">
                        <x-progress-bar
                            :value="$server->memory_usage ?? 0"
                            label="Memória"
                            :showValue="true"
                            variant="auto"
                            height="h-2.5"
                        />
                    </div>

                    {{-- Disk Progress Bar --}}
                    <div class="mb-4">
                        <x-progress-bar
                            :value="$server->disk_usage ?? 0"
                            label="Disco"
                            :showValue="true"
                            variant="auto"
                            height="h-2.5"
                            :thresholds="['success' => 60, 'warning' => 85]"
                        />
                    </div>

                    {{-- Footer --}}
                    @if($server->last_heartbeat)
                        <div class="pt-3 border-t dark:border-gray-700 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-heart class="w-3.5 h-3.5 {{ $server->status->value === 'online' ? 'text-emerald-500 animate-pulse' : '' }}" />
                            <span>Último heartbeat: {{ \Carbon\Carbon::parse($server->last_heartbeat)->diffForHumans() }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Recent deployments --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-rocket-launch class="w-5 h-5 text-gray-500" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Deployments Recentes</h3>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aplicação</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Commit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Quando</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($recentDeployments as $deployment)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-m-cube class="w-4 h-4 text-gray-400" />
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $deployment->application->name ?? 'N/A' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600 dark:text-gray-400">
                                    {{ substr($deployment->commit_sha ?? '', 0, 7) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <x-status-badge :status="$deployment->status" size="sm" dot="true" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center gap-1.5">
                                        <x-heroicon-m-clock class="w-4 h-4 text-gray-400" />
                                        {{ $deployment->created_at->diffForHumans() }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-2 opacity-30 text-gray-400" />
                                    <p class="text-sm text-gray-500">Nenhum deployment recente</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
