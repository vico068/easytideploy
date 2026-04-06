@php
    $deploymentId = $deployment->id;
    $isTerminal = $deployment->status->isTerminal();
    $statusValue = $deployment->status->value;

    // Logs já gravados (para deployments concluídos)
    $existingLogs = [];
    if ($deployment->build_logs) {
        foreach (array_filter(explode("\n", $deployment->build_logs)) as $line) {
            $existingLogs[] = trim($line);
        }
    }

    $statusLabels = [
        'pending' => 'Pendente',
        'building' => 'Compilando',
        'deploying' => 'Implantando',
        'running' => 'Concluído',
        'failed' => 'Falhou',
        'cancelled' => 'Cancelado',
        'rolled_back' => 'Revertido',
    ];
@endphp

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
    .deploy-terminal-dots {
        display: flex;
        gap: 6px;
    }
    .deploy-terminal-dots span {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    .deploy-terminal-dots .red { background: #ef4444; }
    .deploy-terminal-dots .yellow { background: #eab308; }
    .deploy-terminal-dots .green { background: #22c55e; }
    .deploy-terminal-title {
        color: #64748b;
        font-size: 12px;
        font-family: inherit;
    }
    .deploy-terminal-live {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: #38bdf8;
    }
    .deploy-terminal-live .dot {
        width: 6px;
        height: 6px;
        background: #38bdf8;
        border-radius: 50%;
        animation: pulse-live 1.5s ease-in-out infinite;
    }
    @keyframes pulse-live {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(0.8); }
    }
    .deploy-terminal-body {
        padding: 12px;
        max-height: 420px;
        min-height: 200px;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
    .deploy-terminal-body::-webkit-scrollbar {
        width: 6px;
    }
    .deploy-terminal-body::-webkit-scrollbar-track {
        background: transparent;
    }
    .deploy-terminal-body::-webkit-scrollbar-thumb {
        background: #334155;
        border-radius: 3px;
    }
    .log-line {
        display: flex;
        gap: 8px;
        padding: 1px 4px;
        border-radius: 3px;
        transition: background 0.15s;
    }
    .log-line:hover {
        background: rgba(255,255,255,0.03);
    }
    .log-num {
        color: #475569;
        min-width: 28px;
        text-align: right;
        user-select: none;
        font-size: 10px;
        padding-top: 2px;
    }
    .log-text {
        flex: 1;
        word-break: break-all;
        white-space: pre-wrap;
    }
    .log-error { color: #f87171; }
    .log-warn { color: #fbbf24; }
    .log-success { color: #4ade80; }
    .log-info { color: #38bdf8; font-weight: 600; }
    .log-default { color: #cbd5e1; }
    .log-cursor {
        color: #38bdf8;
        animation: blink 1s step-end infinite;
        font-size: 14px;
        margin-left: 36px;
    }
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }
    .deploy-terminal-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: #475569;
    }
    .deploy-terminal-empty svg {
        width: 32px;
        height: 32px;
        margin-bottom: 8px;
        opacity: 0.5;
    }
    .deploy-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid;
    }
    .deploy-status-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }
    .status-pending { background: rgba(100,116,139,0.1); border-color: rgba(100,116,139,0.3); color: #94a3b8; }
    .status-building { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.3); color: #fbbf24; }
    .status-deploying { background: rgba(56,189,248,0.1); border-color: rgba(56,189,248,0.3); color: #38bdf8; }
    .status-running { background: rgba(34,197,94,0.1); border-color: rgba(34,197,94,0.3); color: #4ade80; }
    .status-failed { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); color: #f87171; }
    .status-cancelled, .status-rolled_back { background: rgba(100,116,139,0.1); border-color: rgba(100,116,139,0.3); color: #94a3b8; }
</style>

<div class="space-y-3 -mx-2" id="deployment-logs-{{ $deploymentId }}">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 px-2">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-sm font-semibold text-slate-100">{{ $deployment->application->name }}</span>
                <span class="deploy-status-badge status-{{ $statusValue }}" id="status-badge-{{ $deploymentId }}">
                    <span class="dot"></span>
                    <span id="status-text-{{ $deploymentId }}">{{ $statusLabels[$statusValue] ?? $statusValue }}</span>
                </span>
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
                <span class="deploy-terminal-live" id="live-indicator-{{ $deploymentId }}" style="{{ $isTerminal ? 'display:none' : '' }}">
                    <span class="dot"></span>
                    ao vivo
                </span>
            </div>
            <span class="text-xs text-slate-600 font-mono" id="line-count-{{ $deploymentId }}">{{ count($existingLogs) }} linhas</span>
        </div>
        <div class="deploy-terminal-body" id="terminal-body-{{ $deploymentId }}">
            @if(count($existingLogs) === 0)
                <div class="deploy-terminal-empty" id="empty-state-{{ $deploymentId }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ $isTerminal ? 'Nenhum log disponível' : 'Aguardando logs do build...' }}</span>
                </div>
            @else
                @foreach($existingLogs as $i => $line)
                    <div class="log-line">
                        <span class="log-num">{{ $i + 1 }}</span>
                        <span class="log-text {{ Str::contains(strtolower($line), ['error', 'failed', 'erro']) ? 'log-error' : (Str::contains(strtolower($line), ['warn']) ? 'log-warn' : (Str::contains(strtolower($line), ['success', 'done', 'complete']) ? 'log-success' : (Str::startsWith($line, ['---', '===', 'Step', '>>> ']) ? 'log-info' : 'log-default'))) }}">{{ $line }}</span>
                    </div>
                @endforeach
            @endif
            @if(!$isTerminal)
                <div class="log-cursor" id="cursor-{{ $deploymentId }}">▋</div>
            @endif
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

@if(!$isTerminal)
<script>
(function() {
    const deploymentId = '{{ $deploymentId }}';
    const streamUrl = '{{ route("deployments.logs.stream", $deployment) }}';
    const terminalBody = document.getElementById('terminal-body-' + deploymentId);
    const lineCount = document.getElementById('line-count-' + deploymentId);
    const emptyState = document.getElementById('empty-state-' + deploymentId);
    const cursor = document.getElementById('cursor-' + deploymentId);
    const liveIndicator = document.getElementById('live-indicator-' + deploymentId);
    const statusBadge = document.getElementById('status-badge-' + deploymentId);
    const statusText = document.getElementById('status-text-' + deploymentId);

    let logCount = {{ count($existingLogs) }};
    let autoScroll = true;
    let eventSource = null;
    let reconnectAttempts = 0;
    const maxReconnects = 3;

    const statusLabels = {
        pending: 'Pendente',
        building: 'Compilando',
        deploying: 'Implantando',
        running: 'Concluído',
        failed: 'Falhou',
        cancelled: 'Cancelado',
        rolled_back: 'Revertido'
    };

    function getLineClass(line) {
        const l = line.toLowerCase();
        if (l.includes('error') || l.includes('failed') || l.includes('erro')) return 'log-error';
        if (l.includes('warn')) return 'log-warn';
        if (l.includes('success') || l.includes('done') || l.includes('complete')) return 'log-success';
        if (line.startsWith('---') || line.startsWith('===') || line.startsWith('Step') || line.startsWith('>>> ')) return 'log-info';
        return 'log-default';
    }

    function addLogLine(line) {
        if (!line || line.trim() === '') return;

        if (emptyState) emptyState.remove();

        logCount++;
        const div = document.createElement('div');
        div.className = 'log-line';
        div.innerHTML = '<span class="log-num">' + logCount + '</span><span class="log-text ' + getLineClass(line) + '">' + escapeHtml(line) + '</span>';

        if (cursor) {
            terminalBody.insertBefore(div, cursor);
        } else {
            terminalBody.appendChild(div);
        }

        lineCount.textContent = logCount + ' linhas';

        if (autoScroll) {
            terminalBody.scrollTop = terminalBody.scrollHeight;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function updateStatus(status) {
        statusBadge.className = 'deploy-status-badge status-' + status;
        statusText.textContent = statusLabels[status] || status;

        if (['running', 'failed', 'cancelled', 'rolled_back'].includes(status)) {
            if (cursor) cursor.remove();
            if (liveIndicator) liveIndicator.style.display = 'none';
            closeConnection();
        }
    }

    function closeConnection() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    }

    function connectSSE() {
        if (eventSource) {
            eventSource.close();
        }

        eventSource = new EventSource(streamUrl);

        eventSource.addEventListener('log', function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.line) addLogLine(data.line);
            } catch (err) {
                console.error('Error parsing log event:', err);
            }
        });

        eventSource.addEventListener('status', function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.status) updateStatus(data.status);
            } catch (err) {
                console.error('Error parsing status event:', err);
            }
        });

        eventSource.addEventListener('stage', function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.stage) {
                    addLogLine('>>> Stage: ' + data.stage + ' (' + (data.status || 'started') + ')');
                }
            } catch (err) {
                console.error('Error parsing stage event:', err);
            }
        });

        eventSource.addEventListener('done', function(e) {
            console.log('SSE stream ended:', e.data);
            closeConnection();
        });

        eventSource.addEventListener('timeout', function(e) {
            console.log('SSE stream timeout:', e.data);
            closeConnection();
        });

        eventSource.addEventListener('heartbeat', function(e) {
            // Heartbeat received - connection is alive
            reconnectAttempts = 0;
        });

        eventSource.onerror = function(e) {
            console.error('SSE connection error:', e);

            if (eventSource.readyState === EventSource.CLOSED) {
                closeConnection();

                // Try to reconnect a few times
                if (reconnectAttempts < maxReconnects) {
                    reconnectAttempts++;
                    console.log('Attempting to reconnect... (' + reconnectAttempts + '/' + maxReconnects + ')');
                    setTimeout(connectSSE, 2000 * reconnectAttempts);
                }
            }
        };

        eventSource.onopen = function() {
            console.log('SSE connection established');
            reconnectAttempts = 0;
        };
    }

    // Detectar scroll manual
    terminalBody.addEventListener('scroll', function() {
        const atBottom = (terminalBody.scrollHeight - terminalBody.scrollTop - terminalBody.clientHeight) < 50;
        autoScroll = atBottom;
    });

    // Iniciar conexão SSE
    connectSSE();

    // Cleanup quando modal fechar
    const modal = document.getElementById('deployment-logs-' + deploymentId).closest('[x-data]');
    if (modal) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.removedNodes.length > 0) {
                    closeConnection();
                    observer.disconnect();
                }
            });
        });
        observer.observe(modal.parentNode, { childList: true });
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', closeConnection);
})();
</script>
@endif
