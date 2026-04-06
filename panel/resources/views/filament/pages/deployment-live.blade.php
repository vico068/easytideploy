<x-filament-panels::page>
<div
    class="space-y-6"
    @deployment-finished.window="$wire.set('isActive', false)"
>
    {{-- ============================================================
         HEADER: info do deployment + badge de status
    ============================================================ --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $deployment->application->name ?? 'Aplicação' }}
                </h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                    {{ $deployment->status->getColor() === 'success' ? 'bg-emerald-500/15 text-emerald-400' :
                       ($deployment->status->getColor() === 'warning' ? 'bg-amber-500/15 text-amber-400' :
                       ($deployment->status->getColor() === 'info' ? 'bg-sky-500/15 text-sky-400' :
                       ($deployment->status->getColor() === 'danger' ? 'bg-red-500/15 text-red-400' :
                       'bg-slate-500/15 text-slate-400'))) }}">
                    @if($isActive)
                        <span class="inline-block w-1.5 h-1.5 rounded-full animate-pulse
                            {{ $deployment->status->getColor() === 'warning' ? 'bg-amber-400' :
                               ($deployment->status->getColor() === 'info' ? 'bg-sky-400' : 'bg-slate-400') }}">
                        </span>
                    @endif
                    {{ $deployment->status->getLabel() }}
                </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                @if($deployment->commit_sha)
                    <span class="font-mono">{{ $deployment->short_commit_sha }}</span>
                    @if($deployment->commit_message)
                        · {{ Str::limit($deployment->commit_message, 60) }}
                    @endif
                @else
                    Deploy iniciado {{ $deployment->created_at->diffForHumans() }}
                @endif
            </p>
        </div>

        <a href="{{ \App\Filament\Resources\DeploymentResource::getUrl('view', ['record' => $deployment->id]) }}"
           class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-sky-400 transition-colors duration-200">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Ver detalhes
        </a>
    </div>

    {{-- ============================================================
         STEPPER: etapas do deploy
    ============================================================ --}}
    <div
        x-data="{
            stages: {
                clone:  'pending',
                build:  'pending',
                push:   'pending',
                deploy: 'pending',
            },
            updateStage(stage, status) {
                if (this.stages[stage] !== undefined) {
                    this.stages[stage] = status;
                }
            }
        }"
        @stage-update.window="updateStage($event.detail.stage, $event.detail.status)"
        class="bg-white dark:bg-slate-900/60 border border-gray-200 dark:border-white/[0.07] rounded-2xl p-6"
    >
        <div class="flex items-center justify-between gap-2">
            @foreach([
                ['key' => 'clone',  'label' => 'Clone',     'icon' => 'heroicon-o-arrow-down-tray'],
                ['key' => 'build',  'label' => 'Build',     'icon' => 'heroicon-o-cog-6-tooth'],
                ['key' => 'push',   'label' => 'Push',      'icon' => 'heroicon-o-cloud-arrow-up'],
                ['key' => 'deploy', 'label' => 'Deploy',    'icon' => 'heroicon-o-rocket-launch'],
            ] as $i => $step)
                <div class="flex flex-col items-center gap-2 flex-1"
                     x-data="{ key: '{{ $step['key'] }}' }"
                >
                    {{-- Ícone de status --}}
                    <div class="relative">
                        {{-- pending --}}
                        <div x-show="stages[key] === 'pending'"
                             class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 border-2
                                    border-slate-200 dark:border-slate-700 flex items-center justify-center">
                            <x-dynamic-component :component="$step['icon']" class="w-5 h-5 text-slate-400" />
                        </div>
                        {{-- running --}}
                        <div x-show="stages[key] === 'running'"
                             class="w-10 h-10 rounded-full bg-sky-500/15 border-2 border-sky-500
                                    flex items-center justify-center">
                            <svg class="w-5 h-5 text-sky-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        {{-- success --}}
                        <div x-show="stages[key] === 'success'"
                             class="w-10 h-10 rounded-full bg-emerald-500/15 border-2 border-emerald-500
                                    flex items-center justify-center">
                            <x-heroicon-o-check class="w-5 h-5 text-emerald-400" />
                        </div>
                        {{-- failed --}}
                        <div x-show="stages[key] === 'failed'"
                             class="w-10 h-10 rounded-full bg-red-500/15 border-2 border-red-500
                                    flex items-center justify-center">
                            <x-heroicon-o-x-mark class="w-5 h-5 text-red-400" />
                        </div>
                    </div>

                    <span class="text-xs font-medium"
                          :class="{
                              'text-slate-400 dark:text-slate-500': stages[key] === 'pending',
                              'text-sky-400': stages[key] === 'running',
                              'text-emerald-400': stages[key] === 'success',
                              'text-red-400': stages[key] === 'failed',
                          }">
                        {{ $step['label'] }}
                    </span>
                </div>

                @if($i < 3)
                    <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700 mt-[-1.5rem] max-w-[4rem]"></div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ============================================================
         TERMINAL: logs de build em tempo real via SSE
    ============================================================ --}}
    <div
        x-data="deploymentLogs(
            '{{ $deploymentId }}',
            '{{ config('easydeploy.orchestrator_api_key') }}',
            '{{ url('') }}'
        )"
        x-init="connect()"
        @stage-update.window="$dispatch('stage-update', $event.detail)"
    >
        <div class="bg-slate-900 rounded-2xl border border-slate-700/50 overflow-hidden
                    shadow-2xl shadow-slate-900/50">
            {{-- Header estilo macOS --}}
            <div class="bg-slate-800/80 px-4 py-3 border-b border-slate-700/50
                        flex items-center gap-3 backdrop-blur-sm">
                <div class="flex gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                </div>
                <span class="text-slate-400 text-sm font-medium flex-1">Build Logs</span>

                {{-- Indicador ao vivo --}}
                <div x-show="connected && !finished"
                     class="flex items-center gap-1.5 text-xs text-emerald-400">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    ao vivo
                </div>
                <div x-show="finished"
                     class="flex items-center gap-1.5 text-xs text-slate-500">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                    concluído
                </div>
                <div x-show="!connected && !finished"
                     class="flex items-center gap-1.5 text-xs text-amber-400">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    conectando...
                </div>
            </div>

            {{-- Conteúdo do terminal --}}
            <div
                x-ref="logContainer"
                class="p-4 font-mono text-sm text-slate-300 h-[32rem] overflow-y-auto
                       scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent"
                style="line-height: 1.6;"
            >
                <template x-if="lines.length === 0 && !connected">
                    <div class="text-slate-500 italic">Aguardando conexão...</div>
                </template>

                <template x-for="line in lines" :key="line.id">
                    <div
                        class="leading-relaxed hover:bg-white/[0.02] px-1 -mx-1 rounded transition-colors"
                        :class="{
                            'text-red-400': line.content.toLowerCase().includes('error') || line.content.toLowerCase().includes('failed'),
                            'text-yellow-400': line.content.toLowerCase().includes('warn'),
                            'text-emerald-400': line.content.toLowerCase().includes('successfully') || line.content.toLowerCase().includes('success'),
                            'text-sky-400': line.content.startsWith('Step ') || line.content.startsWith('---'),
                        }"
                        x-text="line.content"
                    ></div>
                </template>
            </div>
        </div>
    </div>
</div>

{{-- Livewire polling: 2s enquanto ativo, para quando concluído --}}
@if($isActive)
    <div wire:poll.2000ms="refreshStatus"></div>
@endif

<script>
function deploymentLogs(deploymentId, apiKey, panelUrl) {
    return {
        lines: [],
        connected: false,
        finished: false,
        lineCount: 0,
        es: null,

        connect() {
            const url = `${panelUrl}/api/internal/deployments/${deploymentId}/build-logs/stream?api_key=${encodeURIComponent(apiKey)}`;
            this.es = new EventSource(url);

            this.es.addEventListener('open', () => {
                this.connected = true;
            });

            this.es.addEventListener('log', (e) => {
                const data = JSON.parse(e.data);
                this.addLine(data.line || '');
            });

            this.es.addEventListener('stage', (e) => {
                const data = JSON.parse(e.data);
                this.$dispatch('stage-update', { stage: data.stage, status: data.status });
            });

            this.es.addEventListener('status', (e) => {
                const data = JSON.parse(e.data);
                // status event is handled by Livewire poll
            });

            this.es.addEventListener('done', () => {
                this.connected = false;
                this.finished = true;
                if (this.es) {
                    this.es.close();
                    this.es = null;
                }
            });

            this.es.onerror = () => {
                this.connected = false;
                if (this.finished) return;
                // Retry after 3 seconds
                if (this.es) {
                    this.es.close();
                    this.es = null;
                }
                setTimeout(() => {
                    if (!this.finished) this.connect();
                }, 3000);
            };
        },

        addLine(content) {
            if (!content && this.lines.length > 0) return; // skip empty lines at start
            this.lines.push({ id: this.lineCount++, content });

            // Keep max 2000 lines to avoid DOM bloat
            if (this.lines.length > 2000) {
                this.lines.splice(0, this.lines.length - 2000);
            }

            // Auto-scroll to bottom
            this.$nextTick(() => {
                const el = this.$refs.logContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    };
}
</script>
</x-filament-panels::page>
