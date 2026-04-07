@php
    $deploymentId = $deployment->id;
    $isTerminal   = $deployment->status->isTerminal();
    $statusValue  = $deployment->status->value;
    $initialLogs  = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($deployment->build_logs ?? '')))));
    $logsUrl      = route('deployments.logs.show', $deployment);
@endphp

{{-- Componente Alpine registrado em public/js/deployment-logs.js via alpine:init --}}

<style>
    .deploy-terminal {
        font-family: 'JetBrains Mono', 'Fira Code', ui-monospace, monospace;
        font-size: 12px;
        line-height: 1.5;
        background: #0a0a0f;
        color: #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.06);
    }
    .deploy-terminal-header {
        background: linear-gradient(to bottom, #1e293b, #0f172a);
        padding: 10px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .deploy-terminal-dots { display: flex; gap: 6px; }
    .deploy-terminal-dots span { width: 12px; height: 12px; border-radius: 50%; }
    .deploy-terminal-dots .red    { background: #ef4444; }
    .deploy-terminal-dots .yellow { background: #eab308; }
    .deploy-terminal-dots .green  { background: #22c55e; }
    .deploy-terminal-title  { color: #64748b; font-size: 12px; font-family: inherit; }
    .deploy-terminal-live {
        display: flex; align-items: center; gap: 6px;
        font-size: 11px; color: #38bdf8;
    }
    .deploy-terminal-live .dot {
        width: 6px; height: 6px; background: #38bdf8;
        border-radius: 50%; animation: pulse-live 1.5s ease-in-out infinite;
    }
    @keyframes pulse-live {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.4; transform: scale(0.8); }
    }
    .deploy-terminal-body {
        padding: 12px;
        max-height: 420px;
        min-height: 200px;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
    .deploy-terminal-body::-webkit-scrollbar { width: 6px; }
    .deploy-terminal-body::-webkit-scrollbar-track { background: transparent; }
    .deploy-terminal-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    .log-line { display: flex; gap: 8px; padding: 1px 4px; border-radius: 3px; transition: background 0.15s; }
    .log-line:hover { background: rgba(255,255,255,0.03); }
    .log-num { color: #475569; min-width: 28px; text-align: right; user-select: none; font-size: 10px; padding-top: 2px; }
    .log-text { flex: 1; word-break: break-all; white-space: pre-wrap; }
    .log-error   { color: #f87171; }
    .log-warn    { color: #fbbf24; }
    .log-success { color: #4ade80; }
    .log-info    { color: #38bdf8; font-weight: 600; }
    .log-default { color: #cbd5e1; }
    .log-cursor  { color: #38bdf8; animation: blink 1s step-end infinite; font-size: 14px; margin-left: 36px; }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
    .deploy-terminal-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 40px; color: #475569;
    }
    .deploy-terminal-empty svg { width: 32px; height: 32px; margin-bottom: 8px; opacity: 0.5; }
    .deploy-status-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 9999px;
        font-size: 11px; font-weight: 600; border: 1px solid;
    }
    .deploy-status-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .status-pending     { background: rgba(100,116,139,0.1); border-color: rgba(100,116,139,0.3); color: #94a3b8; }
    .status-building    { background: rgba(245,158,11,0.1);  border-color: rgba(245,158,11,0.3);  color: #fbbf24; }
    .status-deploying   { background: rgba(56,189,248,0.1);  border-color: rgba(56,189,248,0.3);  color: #38bdf8; }
    .status-running     { background: rgba(34,197,94,0.1);   border-color: rgba(34,197,94,0.3);   color: #4ade80; }
    .status-failed      { background: rgba(239,68,68,0.1);   border-color: rgba(239,68,68,0.3);   color: #f87171; }
    .status-cancelled, .status-rolled_back { background: rgba(100,116,139,0.1); border-color: rgba(100,116,139,0.3); color: #94a3b8; }
    .sse-status     { font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 8px; }
    .sse-connecting { background: rgba(245,158,11,0.2); color: #fbbf24; }
    .sse-connected  { background: rgba(34,197,94,0.2);  color: #4ade80; }
    .sse-error      { background: rgba(239,68,68,0.2);  color: #f87171; }
</style>

<div class="space-y-3 -mx-2"
     x-data="deploymentLogs(
         '{{ $deploymentId }}',
         {{ $isTerminal ? 'true' : 'false' }},
         '{{ $statusValue }}',
         @js($initialLogs),
         '{{ $logsUrl }}'
     )"
     x-on:remove.window="cleanup()">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 px-2">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-sm font-semibold text-slate-100">{{ $deployment->application->name }}</span>
                <span class="deploy-status-badge" :class="'status-' + currentStatus">
                    <span class="dot"></span>
                    <span x-text="statusLabels[currentStatus] || currentStatus"></span>
                </span>
                <span class="sse-status" :class="'sse-' + sseState" x-text="sseText"></span>
            </div>
            <p class="text-xs text-slate-500 flex items-center gap-1.5 flex-wrap">
                @if($deployment->commit_sha)
                    <span class="font-mono bg-slate-800 border border-white/5 px-1.5 py-0.5 rounded text-slate-300">
                        {{ $deployment->short_commit_sha }}
                    </span>
                @endif
                @if($deployment->commit_message)
                    <span class="italic">{{ Str::limit($deployment->commit_message, 60) }}</span>
                @endif
            </p>
        </div>
        <div class="text-right text-xs text-slate-500 flex-shrink-0">
            <div>{{ $deployment->created_at->format('d/m/Y H:i') }}</div>
            @if($deployment->duration)
                <div class="text-slate-400 font-mono">{{ $deployment->duration }}</div>
            @endif
        </div>
    </div>

    {{-- Terminal --}}
    <div class="deploy-terminal mx-2">
        <div class="deploy-terminal-header">
            <div class="flex items-center gap-3">
                <div class="deploy-terminal-dots">
                    <span class="red"></span>
                    <span class="yellow"></span>
                    <span class="green"></span>
                </div>
                <span class="deploy-terminal-title">build output</span>
                <span class="deploy-terminal-live" x-show="!finished">
                    <span class="dot"></span>
                    ao vivo
                </span>
            </div>
            <span class="text-xs text-slate-600 font-mono" x-text="logCount + ' linhas'"></span>
        </div>
        <div class="deploy-terminal-body" id="terminal-body-{{ $deploymentId }}">
            <div class="deploy-terminal-empty" x-show="logCount === 0">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>{{ $isTerminal ? 'Carregando logs...' : 'Aguardando logs do build...' }}</span>
            </div>
            <div class="log-cursor" x-show="!finished" id="cursor-{{ $deploymentId }}">▋</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="flex items-center justify-between px-2 text-xs text-slate-500">
        <div class="flex items-center gap-1.5">
            <span>Disparado por</span>
            <span class="bg-slate-800 border border-white/5 px-2 py-0.5 rounded font-medium text-slate-300">
                {{ $deployment->triggered_by }}
            </span>
        </div>
        <div class="font-mono">{{ $deployment->created_at->format('d/m/Y H:i:s') }}</div>
    </div>
</div>
