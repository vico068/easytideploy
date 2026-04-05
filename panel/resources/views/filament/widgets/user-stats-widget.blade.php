{{-- Stats Overview — Dashboard Principal --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4">

    {{-- Aplicações --}}
    <div class="group relative overflow-hidden rounded-2xl
                bg-white dark:bg-slate-800/60 p-5
                border border-gray-100 dark:border-slate-700/50
                hover:border-sky-300/60 dark:hover:border-sky-500/40
                hover:shadow-lg hover:shadow-sky-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-sky-500/0 to-cyan-500/0
                    group-hover:from-sky-500/5 group-hover:to-cyan-500/3
                    transition-all duration-500 rounded-2xl pointer-events-none"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                    Aplicações
                </span>
                <div class="p-2 rounded-xl bg-sky-50 dark:bg-sky-500/15 ring-1 ring-sky-200/50 dark:ring-sky-500/20">
                    <x-heroicon-o-cube class="w-4 h-4 text-sky-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $totalApps }}</div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">total cadastradas</p>
        </div>
    </div>

    {{-- Containers --}}
    <div class="group relative overflow-hidden rounded-2xl
                bg-white dark:bg-slate-800/60 p-5
                border border-gray-100 dark:border-slate-700/50
                hover:border-cyan-300/60 dark:hover:border-cyan-500/40
                hover:shadow-lg hover:shadow-cyan-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/0 to-sky-500/0
                    group-hover:from-cyan-500/5 group-hover:to-sky-500/3
                    transition-all duration-500 rounded-2xl pointer-events-none"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                    Containers
                </span>
                <div class="p-2 rounded-xl bg-cyan-50 dark:bg-cyan-500/15 ring-1 ring-cyan-200/50 dark:ring-cyan-500/20">
                    <x-heroicon-o-server-stack class="w-4 h-4 text-cyan-500" />
                </div>
            </div>
            <div class="flex items-end justify-between">
                <div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $runningContainers }}</div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">rodando agora</p>
                </div>
                @if($runningContainers > 0)
                    <div class="flex items-center gap-1.5 pb-1">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-[10px] font-semibold text-emerald-500 dark:text-emerald-400">live</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sucesso --}}
    <div class="group relative overflow-hidden rounded-2xl
                bg-white dark:bg-slate-800/60 p-5
                border border-gray-100 dark:border-slate-700/50
                hover:border-emerald-300/60 dark:hover:border-emerald-500/40
                hover:shadow-lg hover:shadow-emerald-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/0 to-green-500/0
                    group-hover:from-emerald-500/5 group-hover:to-green-500/3
                    transition-all duration-500 rounded-2xl pointer-events-none"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                    Ativos
                </span>
                <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 ring-1 ring-emerald-200/50 dark:ring-emerald-500/20">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $successDeploys }}</div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">deploys rodando</p>
        </div>
    </div>

    {{-- Hoje --}}
    <div class="group relative overflow-hidden rounded-2xl
                bg-white dark:bg-slate-800/60 p-5
                border border-gray-100 dark:border-slate-700/50
                hover:border-amber-300/60 dark:hover:border-amber-500/40
                hover:shadow-lg hover:shadow-amber-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-amber-500/0 to-orange-500/0
                    group-hover:from-amber-500/5 group-hover:to-orange-500/3
                    transition-all duration-500 rounded-2xl pointer-events-none"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                    Hoje
                </span>
                <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-500/15 ring-1 ring-amber-200/50 dark:ring-amber-500/20">
                    <x-heroicon-o-calendar-days class="w-4 h-4 text-amber-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $todayDeploys }}</div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">deploys hoje</p>
        </div>
    </div>

    {{-- Falhas --}}
    <div class="group relative overflow-hidden rounded-2xl
                bg-white dark:bg-slate-800/60 p-5
                border border-gray-100 dark:border-slate-700/50
                hover:border-red-300/60 dark:hover:border-red-500/40
                hover:shadow-lg hover:shadow-red-500/10
                transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-red-500/0 to-rose-500/0
                    group-hover:from-red-500/5 group-hover:to-rose-500/3
                    transition-all duration-500 rounded-2xl pointer-events-none"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                    Falhas
                </span>
                <div class="p-2 rounded-xl bg-red-50 dark:bg-red-500/15 ring-1 ring-red-200/50 dark:ring-red-500/20">
                    <x-heroicon-o-x-circle class="w-4 h-4 text-red-500" />
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $failedDeploys }}</div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">com falha</p>
        </div>
    </div>

</div>
