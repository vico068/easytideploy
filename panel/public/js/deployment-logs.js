/**
 * deployment-logs.js
 *
 * Registra o componente Alpine.js `deploymentLogs` globalmente usando
 * `alpine:init` — garante disponibilidade antes que Alpine processe
 * qualquer elemento `x-data`, inclusive os injetados via Livewire morphdom.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('deploymentLogs', (deploymentId, streamUrl, isTerminal, initialStatus) => ({
        deploymentId,
        streamUrl,
        isTerminal,

        logCount:      0,
        autoScroll:    true,
        eventSource:   null,
        finished:      isTerminal,
        seenLines:     new Set(),
        currentStatus: initialStatus || 'pending',
        sseState:      'connecting',
        sseText:       'conectando...',

        statusLabels: {
            pending:     'Pendente',
            building:    'Compilando',
            deploying:   'Implantando',
            running:     'Concluído',
            failed:      'Falhou',
            cancelled:   'Cancelado',
            rolled_back: 'Revertido',
        },

        init() {
            const body = this.terminalBody();
            if (body) {
                body.addEventListener('scroll', () => {
                    const atBottom = (body.scrollHeight - body.scrollTop - body.clientHeight) < 50;
                    this.autoScroll = atBottom;
                });
            }
            this.connectSSE();
        },

        destroy() {
            this.cleanup();
        },

        terminalBody() {
            return document.getElementById('terminal-body-' + this.deploymentId);
        },

        getLineClass(line) {
            const l = line.toLowerCase();
            if (l.includes('error') || l.includes('failed') || l.includes('erro')) return 'log-error';
            if (l.includes('warn')) return 'log-warn';
            if (l.includes('success') || l.includes('done') || l.includes('complete')) return 'log-success';
            if (line.startsWith('---') || line.startsWith('===') ||
                line.startsWith('Step') || line.startsWith('>>> ')) return 'log-info';
            return 'log-default';
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        addLogLine(line) {
            if (!line || line.trim() === '') return;

            const hash = line.substring(0, 120);
            if (this.seenLines.has(hash)) return;
            this.seenLines.add(hash);

            this.logCount++;

            const body   = this.terminalBody();
            const cursor = document.getElementById('cursor-' + this.deploymentId);

            const el = document.createElement('div');
            el.className = 'log-line';
            el.innerHTML =
                '<span class="log-num">' + this.logCount + '</span>' +
                '<span class="log-text ' + this.getLineClass(line) + '">' +
                this.escapeHtml(line) + '</span>';

            if (cursor && cursor.parentNode === body) {
                body.insertBefore(el, cursor);
            } else if (body) {
                body.appendChild(el);
            }

            if (this.autoScroll && body) {
                body.scrollTop = body.scrollHeight;
            }
        },

        updateStatus(status) {
            this.currentStatus = status;
            if (['running', 'failed', 'cancelled', 'rolled_back'].includes(status)) {
                this.finished = true;
            }
        },

        finishStream() {
            this.finished  = true;
            this.sseState  = 'connected';
            this.sseText   = 'completo';
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
        },

        connectSSE() {
            this.sseState = 'connecting';
            this.sseText  = 'conectando...';

            this.eventSource = new EventSource(this.streamUrl);

            this.eventSource.onopen = () => {
                this.sseState = 'connected';
                this.sseText  = 'conectado';
            };

            this.eventSource.onerror = () => {
                if (this.eventSource && this.eventSource.readyState === EventSource.CLOSED) {
                    this.sseState = 'error';
                    this.sseText  = 'desconectado';
                } else {
                    this.sseState = 'connecting';
                    this.sseText  = 'reconectando...';
                }
            };

            this.eventSource.addEventListener('log', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data.line) this.addLogLine(data.line);
                } catch (_) {}
            });

            this.eventSource.addEventListener('status', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data.status) this.updateStatus(data.status);
                } catch (_) {}
            });

            this.eventSource.addEventListener('stage', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data.stage && data.status) {
                        this.addLogLine('>>> ' + data.stage.toUpperCase() + ': ' + data.status);
                    }
                } catch (_) {}
            });

            this.eventSource.addEventListener('done', () => {
                this.finishStream();
            });
        },

        cleanup() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
        },
    }));
});
