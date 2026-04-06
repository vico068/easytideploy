@php
    $isTerminal  = $deployment->status->isTerminal();
    $statusValue = $deployment->status->value;

    // Logs gravados na coluna build_logs
    $logLines = [];
    if ($deployment->build_logs) {
        foreach (array_filter(explode("\n", $deployment->build_logs)) as $line) {
            $logLines[] = trim($line);
        }
    }

    // Status config
    $statusConfig = match($statusValue) {
        'pending'    => ['bg' => 'bg-slate-700/50', 'border' => 'border-slate-600/50', 'text' => 'text-slate-400', 'label' => 'Pendente'],
        'building'   => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/30', 'text' => 'text-amber-400', 'label' => 'Compilando'],
        'deploying'  => ['bg' => 'bg-sky-500/10', 'border' => 'border-sky-500/30', 'text' => 'text-sky-400', 'label' => 'Implantando'],
        'running'    => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'label' => 'Concluído'],
        'failed'     => ['bg' => 'bg-red-500/10', 'border' => 'border-red-500/30', 'text' => 'text-red-400', 'label' => 'Falhou'],
        'cancelled'  => ['bg' => 'bg-slate-700/50', 'border' => 'border-slate-600/50', 'text' => 'text-slate-400', 'label' => 'Cancelado'],
        'rolled_back'=> ['bg' => 'bg-slate-700/50', 'border' => 'border-slate-600/50', 'text' => 'text-slate-400', 'label' => 'Revertido'],
        default      => ['bg' => 'bg-slate-700/50', 'border' => 'border-slate-600/50', 'text' => 'text-slate-400', 'label' => $statusValue],
    };

    // Função para classificar linha de log
    $getLineClass = function($line) {
        $l = strtolower($line);
        if (str_contains($l, 'error') || str_contains($l, 'failed') || str_contains($l, 'erro')) {
            return 'log-line-error';
        }
        if (str_contains($l, 'warn') || str_contains($l, 'warning')) {
            return 'log-line-warning';
        }
        if (str_starts_with($l, '---') || str_starts_with($l, '===') || str_starts_with($l, 'step') || str_starts_with($l, '>>> ')) {
            return 'log-line-header';
        }
        if (str_contains($l, 'success') || str_contains($l, 'sucesso') || str_contains($l, 'done') || str_contains($l, 'complete')) {
            return 'log-line-success';
        }
        return 'log-line-default';
    };
@endphp

<style>
    .deployment-logs-terminal {
        font-family: 'JetBrains Mono', 'Fira Code', ui-monospace, monospace;
        font-size: 12px;
        line-height: 1.6;
    }
    .deployment-logs-terminal::-webkit-scrollbar {
        width: 6px;
    }
    .deployment-logs-terminal::-webkit-scrollbar-track {
        background: transparent;
    }
    .deployment-logs-terminal::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 3px;
    }
    .log-line {
        display: flex;
        gap: 8px;
        padding: 2px 4px;
        border-radius: 4px;
        transition: background-color 0.15s;
    }
    .log-line:hover {
        background-color: rgba(255, 255, 255, 0.02);
    }
    .log-line-number {
        flex-shrink: 0;
        width: 32px;
        text-align: right;
        color: #475569;
        font-size: 10px;
        user-select: none;
        padding-top: 2px;
    }
    .log-line-content {
        flex: 1;
        word-break: break-all;
        white-space: pre-wrap;
    }
    .log-line-error { color: #f87171; }
    .log-line-warning { color: #fbbf24; }
    .log-line-header { color: #7dd3fc; font-weight: 600; }
    .log-line-success { color: #4ade80; }
    .log-line-default { color: #cbd5e1; }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .pulse-dot {
        animation: pulse-dot 1.5s ease-in-out infinite;
    }
</style>

<div class="space-y-3 -mx-2" @if(!$isTerminal) wire:poll.2s @endif>

    {{-- Header: app + commit + status --}}
    <div class="flex items-start justify-between gap-4 px-2">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-sm font-semibold text-slate-100">{{ $deployment->application->name }}</span>

                {{-- Status badge --}}
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold border {{ $statusConfig['bg'] }} {{ $statusConfig['border'] }} {{ $statusConfig['text'] }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current @if(!$isTerminal) pulse-dot @endif"></span>
                    {{ $statusConfig['label'] }}
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

    {{-- Terminal --}}
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

                @if(!$isTerminal)
                    <span class="flex items-center gap-1 text-xs text-sky-400 ml-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400 pulse-dot inline-block"></span>
                        ao vivo
                    </span>
                @elseif($statusValue === 'running')
                    <span class="text-xs text-emerald-400 ml-1">concluído</span>
                @elseif($statusValue === 'failed')
                    <span class="text-xs text-red-400 ml-1">falhou</span>
                @endif
            </div>

            <span class="text-xs text-slate-600 font-mono">{{ count($logLines) }} linhas</span>
        </div>

        {{-- Log lines --}}
        <div class="deployment-logs-terminal p-3 max-h-[400px] min-h-[160px] overflow-y-auto">
            @if(count($logLines) === 0)
                <div class="flex flex-col items-center justify-center py-10 text-slate-700">
                    <svg class="w-8 h-8 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-slate-500 text-xs">
                        @if($isTerminal)
                            Nenhum log disponível
                        @else
                            Aguardando logs do build...
                        @endif
                    </p>
                </div>
            @else
                @foreach($logLines as $index => $line)
                    <div class="log-line">
                        <span class="log-line-number">{{ $index + 1 }}</span>
                        <span class="log-line-content {{ $getLineClass($line) }}">{{ $line }}</span>
                    </div>
                @endforeach

                {{-- Cursor piscante quando ativo --}}
                @if(!$isTerminal)
                    <div class="pl-10 pt-1">
                        <span class="text-sky-400 pulse-dot text-sm">▋</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Footer --}}
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
