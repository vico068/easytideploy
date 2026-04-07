/**
 * deployment-logs.js
 *
 * Registra o componente Alpine.js `deploymentLogs` globalmente usando
 * `alpine:init` — garante disponibilidade antes que Alpine processe
 * qualquer elemento `x-data`, inclusive os injetados via Livewire morphdom.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('deploymentLogs', (deploymentId, isTerminal, initialStatus, initialLogs = [], logsUrl = null) => ({
        deploymentId,
        isTerminal,
        initialLogs,
        logsUrl,

        logCount:      0,
        autoScroll:    true,
        channel:       null,
        connectTimer:  null,
        finished:      isTerminal,
        seenLines:     new Set(),
        currentStatus: initialStatus || 'pending',
        sseState:      'connecting',
        sseText:       'aguardando socket...',

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

            // Preload persisted logs so users opening a finished deployment still see history.
            for (const line of this.initialLogs) {
                this.addLogLine(line);
            }

            if (this.finished) {
                this.sseState = 'connected';
                this.sseText = 'completo';
                return;
            }

            this.connectEcho();
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

        addSystemLine(line) {
            // System lines should always be rendered, even if a similar hash exists.
            this.logCount++;

            const body   = this.terminalBody();
            const cursor = document.getElementById('cursor-' + this.deploymentId);

            const el = document.createElement('div');
            el.className = 'log-line';
            el.innerHTML =
                '<span class="log-num">' + this.logCount + '</span>' +
                '<span class="log-text log-info">' + this.escapeHtml(line) + '</span>';

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
            this.cleanup();

            if (this.logCount === 0) {
                this.fetchPersistedLogs();
            }
        },

        fetchPersistedLogs() {
            if (!this.logsUrl) {
                this.addSystemLine('>>> Nenhum log persistido encontrado para este deploy.');
                return;
            }

            fetch(this.logsUrl, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Nao foi possivel carregar logs persistidos.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    const logs = String(payload?.logs || '');
                    const lines = logs.split(/\r\n|\r|\n/).map((line) => line.trim()).filter(Boolean);

                    if (lines.length === 0) {
                        this.addSystemLine('>>> Nenhum log persistido encontrado para este deploy.');
                        return;
                    }

                    this.addSystemLine('>>> Recuperando logs persistidos...');
                    for (const line of lines) {
                        this.addLogLine(line);
                    }
                })
                .catch((error) => {
                    this.addSystemLine('>>> Falha ao recuperar logs persistidos: ' + error.message);
                });
        },

        connectEcho() {
            this.sseState = 'connecting';
            this.sseText  = 'conectando websocket...';

            const bindChannel = () => {
                if (!window.Echo) {
                    return false;
                }

                this.channel = window.Echo.private('deployment.' + this.deploymentId)
                    .listen('.BuildLogReceived', (event) => {
                        if (event?.line) {
                            this.addLogLine(event.line);
                        }
                    })
                    .listen('.DeploymentStageChanged', (event) => {
                        if (event?.stage && event?.status) {
                            this.addLogLine('>>> ' + event.stage.toUpperCase() + ': ' + event.status);
                        }
                    })
                    .listen('.DeploymentStatusChanged', (event) => {
                        if (event?.status) {
                            this.updateStatus(event.status);
                            this.addSystemLine('>>> STATUS: ' + String(event.status).toUpperCase());

                            if (event?.error) {
                                this.addSystemLine('>>> ERRO: ' + event.error);
                            }

                            if (['running', 'failed', 'cancelled', 'rolled_back'].includes(event.status)) {
                                this.finishStream();
                            }
                        }
                    });

                this.sseState = 'connected';
                this.sseText = 'websocket ativo';
                return true;
            };

            if (bindChannel()) {
                return;
            }

            let attempts = 0;
            const maxAttempts = 20;
            this.connectTimer = setInterval(() => {
                attempts++;

                if (bindChannel()) {
                    clearInterval(this.connectTimer);
                    this.connectTimer = null;
                    return;
                }

                if (attempts >= maxAttempts) {
                    clearInterval(this.connectTimer);
                    this.connectTimer = null;
                    this.sseState = 'error';
                    this.sseText = 'falha ao conectar websocket';
                }
            }, 500);
        },

        cleanup() {
            if (this.connectTimer) {
                clearInterval(this.connectTimer);
                this.connectTimer = null;
            }

            if (this.channel && window.Echo) {
                window.Echo.leave('deployment.' + this.deploymentId);
                this.channel = null;
            }
        },
    }));
});
