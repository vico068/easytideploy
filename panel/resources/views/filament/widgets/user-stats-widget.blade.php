<div class="grid grid-cols-2 lg:grid-cols-5 gap-4">

    {{-- Apps --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                border border-gray-100 dark:border-white/5
                hover:border-sky-200 dark:hover:border-sky-500/30
                hover:shadow-lg hover:shadow-sky-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-sky-500/0 to-cyan-500/0
                    group-hover:from-sky-500/5 group-hover:to-cyan-500/5 transition-all duration-500 rounded-2xl"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aplicações</span>
                <div class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-500/10">
                    <x-heroicon-o-cube class="w-4 h-4 text-sky-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $totalApps }}</div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">total cadastradas</p>
        </div>
    </div>

    {{-- Containers --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                border border-gray-100 dark:border-white/5
                hover:border-cyan-200 dark:hover:border-cyan-500/30
                hover:shadow-lg hover:shadow-cyan-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/0 to-sky-500/0
                    group-hover:from-cyan-500/5 group-hover:to-sky-500/5 transition-all duration-500 rounded-2xl"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Containers</span>
                <div class="p-1.5 rounded-lg bg-cyan-50 dark:bg-cyan-500/10">
                    <x-heroicon-o-server-stack class="w-4 h-4 text-cyan-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $runningContainers }}</div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">rodando agora</p>
        </div>
    </div>

    {{-- Sucesso --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                border border-gray-100 dark:border-white/5
                hover:border-emerald-200 dark:hover:border-emerald-500/30
                hover:shadow-lg hover:shadow-emerald-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/0 to-green-500/0
                    group-hover:from-emerald-500/5 group-hover:to-green-500/5 transition-all duration-500 rounded-2xl"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sucesso</span>
                <div class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $successDeploys }}</div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">deploys ativos</p>
        </div>
    </div>

    {{-- Hoje --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                border border-gray-100 dark:border-white/5
                hover:border-amber-200 dark:hover:border-amber-500/30
                hover:shadow-lg hover:shadow-amber-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-amber-500/0 to-orange-500/0
                    group-hover:from-amber-500/5 group-hover:to-orange-500/5 transition-all duration-500 rounded-2xl"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hoje</span>
                <div class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-500/10">
                    <x-heroicon-o-calendar-days class="w-4 h-4 text-amber-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $todayDeploys }}</div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">deployments hoje</p>
        </div>
    </div>

    {{-- Falhas --}}
    <div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-5
                border border-gray-100 dark:border-white/5
                hover:border-red-200 dark:hover:border-red-500/30
                hover:shadow-lg hover:shadow-red-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-red-500/0 to-rose-500/0
                    group-hover:from-red-500/5 group-hover:to-rose-500/5 transition-all duration-500 rounded-2xl"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Falhas</span>
                <div class="p-1.5 rounded-lg bg-red-50 dark:bg-red-500/10">
                    <x-heroicon-o-x-circle class="w-4 h-4 text-red-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ $failedDeploys }}</div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">deployments com falha</p>
        </div>
    </div>

</div>
