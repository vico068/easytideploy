@php
    $isTerminal  = $deployment->status->isTerminal();
    $statusValue = $deployment->status->value;

    // Logs já gravados na coluna build_logs (deployments concluídos)
    $existingLogs = [];
    if ($deployment->build_logs) {
        foreach (array_filter(explode("\n", $deployment->build_logs)) as $line) {
            $existingLogs[] = ['line' => trim($line), 'stage' => 'log'];
        }
    }

    $stageOrder = ['clone', 'build', 'push', 'deploy'];
@endphp

<div
    x-data="{
        logs: @js($existingLogs),
        status: '{{ $statusValue }}',
        currentStage: '',
        completedStages: [],
        isTerminal: {{ $isTerminal ? 'true' : 'false' }},
        autoScroll: true,
        channel: null,

        stageOrder: ['clone', 'build', 'push', 'deploy'],

        stageIndex(stage) {
            return this.stageOrder.indexOf(stage);
        },

        stageState(stage) {
            if (this.completedStages.includes(stage)) return 'done';
            if (this.currentStage === stage && !this.isTerminal) return 'active';
            const cur = this.stageIndex(this.currentStage);
            if (cur > -1 && this.stageIndex(stage) < cur) return 'done';
            return 'pending';
        },

        lineClass(log) {
            const l = (log.line || '').toLowerCase();
            if (l.includes('error') || l.includes('failed') || l.includes('erro')) return 'text-red-400';
            if (l.includes('warn') || l.includes('warning')) return 'text-amber-400';
            if (l.startsWith('---') || l.startsWith('===') || l.startsWith('step') || l.startsWith('>>> ')) return 'text-sky-300 font-semibold';
            if (l.includes('success') || l.includes('sucesso') || l.includes('done') || l.includes('complete')) return 'text-emerald-400';
            return 'text-slate-300';
        },

        init() {
            this.$nextTick(() => this.scrollToBottom());

            if (this.isTerminal || !window.Echo) return;

            this.channel = window.Echo.private('deployment.{{ $deployment->id }}');

            this.channel.listen('.BuildLogReceived', (e) => {
                this.logs.push({ line: e.line ?? '', stage: e.stage ?? 'log' });
                this.currentStage = e.stage ?? this.currentStage;
                if (this.autoScroll) this.$nextTick(() => this.scrollToBottom());
            });

            this.channel.listen('.DeploymentStageChanged', (e) => {
                if (!e.stage) return;
                if (['done', 'success', 'completed'].includes(e.status ?? '')) {
                    if (!this.completedStages.includes(e.stage)) {
                        this.completedStages.push(e.stage);
                    }
                } else {
                    this.currentStage = e.stage;
                }
            });

            this.channel.listen('.DeploymentStatusChanged', (e) => {
                this.status   = e.status ?? this.status;
                this.isTerminal = ['running', 'failed', 'cancelled', 'rolled_back'].includes(e.status ?? '');
                if (this.isTerminal && window.Echo) {
                    window.Echo.leave('deployment.{{ $deployment->id }}');
                    this.channel = null;
                }
            });
        },

        // Chamado pelo Alpine quando o elemento é removido do DOM (modal fecha)
        destroy() {
            if (this.channel && window.Echo) {
                window.Echo.leave('deployment.{{ $deployment->id }}');
            }
        },

        scrollToBottom() {
            const el = this.$refs.terminal;
            if (el) el.scrollTop = el.scrollHeight;
        },

        onTerminalScroll() {
            const el = this.$refs.terminal;
            if (!el) return;
            // Re-ativa auto-scroll se usuário voltou ao final
            this.autoScroll = (el.scrollHeight - el.scrollTop - el.clientHeight) < 48;
        },
    }"
    class="space-y-3 -mx-2"
>

    {{-- ── Header: app + commit + status ──────────────────────────────── --}}
    <div class="flex items-start justify-between gap-4 px-2">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-sm font-semibold text-slate-100">{{ $deployment->application->name }}</span>

                {{-- Status badge — atualiza em tempo real --}}
                <span
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold border transition-all duration-300"
                    :class="{
                        'bg-slate-700/50 border-slate-600/50 text-slate-400': ['pending','cancelled','rolled_back'].includes(status),
                        'bg-amber-500/10 border-amber-500/30 text-amber-400': status === 'building',
                        'bg-sky-500/10 border-sky-500/30 text-sky-400': status === 'deploying',
                        'bg-emerald-500/10 border-emerald-500/30 text-emerald-400': status === 'running',
                        'bg-red-500/10 border-red-500/30 text-red-400': status === 'failed',
                    }"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-current" :class="{ 'animate-pulse': !isTerminal }"></span>
                    <span x-text="{
                        pending: 'Pendente',
                        building: 'Compilando',
                        deploying: 'Implantando',
                        running: 'Em execução',
                        failed: 'Falhou',
                        cancelled: 'Cancelado',
                        rolled_back: 'Revertido',
                    }[status] ?? status"></span>
                </span>
            </div>

            <p class="text-xs text-slate-500 flex items-center gap-1.5 flex-wrap">
                @if($deployment->commit_sha)
                    <span class="font-mono bg-slate-800 border border-white/5 px-1.5 py-0.5 rounded text-slate-300">
                        {{ $deployment->short_commit_sha }}
                    </span>
                @endif
                @if($deployment->commit_message)
                    <span class="italic text-slate-500">{{ Str::limit($deployment->commit_message, 60) }}</span>
                @endif
            </p>
        </div>

        <div class="text-right text-xs text-slate-500 flex-shrink-0 space-y-0.5">
            <div>{{ $deployment->created_at->format('d/m/Y H:i') }}</div>
            @if($deployment->duration)
                <div class="text-slate-400 font-mono">{{ $deployment->duration }}</div>
            @endif
        </div>
    </div>

    {{-- ── Stage progress ──────────────────────────────────────────────── --}}
    <div class="mx-2 flex items-center bg-slate-900/80 rounded-xl px-4 py-2.5 border border-white/[0.05]">
        @foreach(['clone', 'build', 'push', 'deploy'] as $idx => $stage)
            <div class="flex items-center flex-1 min-w-0">
                {{-- Step --}}
                <div
                    class="flex items-center gap-1.5 text-xs font-medium transition-all duration-300 whitespace-nowrap"
                    :class="{
                        'text-emerald-400': stageState('{{ $stage }}') === 'done',
                        'text-sky-400': stageState('{{ $stage }}') === 'active',
                        'text-slate-500': stageState('{{ $stage }}') === 'pending',
                    }"
                >
                    <div
                        class="w-5 h-5 rounded-full flex items-center justify-center border transition-all duration-300 flex-shrink-0"
                        :class="{
                            'bg-emerald-500/20 border-emerald-500/50 text-emerald-400':  stageState('{{ $stage }}') === 'done',
                            'bg-sky-500/20 border-sky-500/50 ring-2 ring-sky-500/15 text-sky-400': stageState('{{ $stage }}') === 'active',
                            'bg-slate-800 border-slate-700 text-slate-600':              stageState('{{ $stage }}') === 'pending',
                        }"
                    >
                        <template x-if="stageState('{{ $stage }}') === 'done'">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </template>
                        <template x-if="stageState('{{ $stage }}') === 'active'">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span>
                        </template>
                        <template x-if="stageState('{{ $stage }}') === 'pending'">
                            <span class="text-[9px]">{{ $idx + 1 }}</span>
                        </template>
                    </div>
                    <span class="hidden sm:inline">{{ ucfirst($stage) }}</span>
                </div>

                @if($idx < 3)
                    <div class="flex-1 h-px mx-2 transition-colors duration-500"
                        :class="{
                            'bg-emerald-500/40': stageState('{{ $stage }}') === 'done',
                            'bg-slate-700':      stageState('{{ $stage }}') !== 'done',
                        }"
                    ></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ── Terminal ─────────────────────────────────────────────────────── --}}
    <div class="mx-2 bg-slate-950 rounded-xl border border-white/[0.05] overflow-hidden">

        {{-- Terminal titlebar --}}
        <div class="bg-slate-900/80 px-4 py-2 border-b border-white/[0.05] flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="flex gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-red-500/70"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-500/70"></div>
                    <div class="w-3 h-3 rounded-full bg-emerald-500/70"></div>
                </div>
                <span class="text-slate-500 text-xs font-mono ml-1">build output</span>

                {{-- Indicador ao vivo --}}
                <span x-show="!isTerminal" class="flex items-center gap-1 text-xs text-sky-400 ml-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse inline-block"></span>
                    ao vivo
                </span>
                <span x-show="isTerminal && status === 'running'" class="text-xs text-emerald-400 ml-1">
                    concluído
                </span>
                <span x-show="isTerminal && status === 'failed'" class="text-xs text-red-400 ml-1">
                    falhou
                </span>
            </div>

            {{-- Contador de linhas + auto-scroll toggle --}}
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-600 font-mono" x-text="logs.length + ' linhas'"></span>
                <button
                    @click="autoScroll = !autoScroll; if (autoScroll) scrollToBottom()"
                    class="text-xs flex items-center gap-1 transition-colors"
                    :class="autoScroll ? 'text-sky-400' : 'text-slate-600 hover:text-slate-400'"
                    title="Auto-scroll"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Log lines --}}
        <div
            x-ref="terminal"
            @scroll="onTerminalScroll()"
            class="p-3 font-mono text-xs max-h-[400px] min-h-[160px] overflow-y-auto"
            style="scrollbar-width: thin; scrollbar-color: #1e293b transparent;"
        >
            {{-- Empty state --}}
            <template x-if="logs.length === 0">
                <div class="flex flex-col items-center justify-center py-10 text-slate-700">
                    <svg class="w-8 h-8 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-slate-500 text-xs">Aguardando logs do build...</p>
                </div>
            </template>

            {{-- Log linhas --}}
            <template x-for="(log, i) in logs" :key="i">
                <div class="flex gap-2 leading-5 group hover:bg-white/[0.015] rounded px-1 -mx-1">

                    {{-- Número da linha --}}
                    <span class="flex-shrink-0 text-[10px] text-slate-700 group-hover:text-slate-600 w-7 text-right select-none pt-px"
                        x-text="i + 1">
                    </span>

                    {{-- Badge de stage --}}
                    <span
                        class="flex-shrink-0 text-[9px] px-1 py-px rounded font-semibold self-start mt-[3px] leading-none uppercase tracking-wide"
                        :class="{
                            'bg-sky-500/15 text-sky-500':     log.stage === 'clone',
                            'bg-amber-500/15 text-amber-500': log.stage === 'build',
                            'bg-purple-500/15 text-purple-400': log.stage === 'push',
                            'bg-emerald-500/15 text-emerald-500': log.stage === 'deploy',
                            'bg-slate-800 text-slate-500':    !['clone','build','push','deploy'].includes(log.stage),
                        }"
                        x-text="log.stage || 'log'"
                    ></span>

                    {{-- Texto da linha --}}
                    <span
                        class="break-all whitespace-pre-wrap flex-1 transition-colors"
                        :class="lineClass(log)"
                        x-text="log.line"
                    ></span>
                </div>
            </template>

            {{-- Cursor piscante quando ativo --}}
            <template x-if="!isTerminal && logs.length > 0">
                <div class="pl-10 pt-0.5">
                    <span class="text-sky-400 animate-pulse text-sm">▋</span>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Footer ───────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between px-2 text-xs text-slate-500">
        <div class="flex items-center gap-1.5">
            <span>Disparado por</span>
            <span class="bg-slate-800 border border-white/[0.05] px-2 py-0.5 rounded font-medium text-slate-300">
                {{ $deployment->triggered_by }}
            </span>
        </div>
        <div class="font-mono">
            {{ $deployment->created_at->format('d/m/Y H:i:s') }}
        </div>
    </div>

</div>
