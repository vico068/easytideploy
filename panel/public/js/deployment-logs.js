/**
 * deployment-logs.js
 *
 * Registra o componente Alpine.js `deploymentLogs` globalmente usando
 * `alpine:init` — garante disponibilidade antes que Alpine processe
 * qualquer elemento `x-data`, inclusive os injetados via Livewire morphdom.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('deploymentLogs', (deploymentId, isTerminal, initialStatus, initialLogs = [], logsUrl = null, applicationId = null, streamUrl = null) => ({
        deploymentId,
        isTerminal,
        initialLogs,
        logsUrl,
        applicationId,
        streamUrl,

        logCount:      0,
        autoScroll:    true,
        channel:       null,
        appChannel:    null,
        sse:           null,
        connectTimer:  null,
        watchdogTimer: null,
        finished:      isTerminal,
        seenLines:     new Set(),
        currentStatus: initialStatus || 'pending',
        lastStatusEvent: null,
        lastActivityAt: Date.now(),
        sseState:      'connecting',
        sseText:       'aguardando socket...',
        streamedLogLines: 0,

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
                this.addLogLine(line, false);
            }

            this.scrollToBottom(true);

            if (this.finished) {
                this.sseState = 'connected';
                this.sseText = 'completo';
                return;
            }

            this.connectEcho();
            this.startRealtimeWatchdog();
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

        addLogLine(line, countAsStream = true) {
            if (!line || line.trim() === '') return;

            this.markActivity();

            const hash = line.substring(0, 120);
            if (this.seenLines.has(hash)) return;
            this.seenLines.add(hash);

            this.logCount++;
            if (countAsStream) {
                this.streamedLogLines++;
            }

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

            if (this.autoScroll) {
                this.scrollToBottom();
            }
        },

        addSystemLine(line) {
            // System lines should always be rendered, even if a similar hash exists.
            this.markActivity();
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

            if (this.autoScroll) {
                this.scrollToBottom();
            }
        },

        scrollToBottom(immediate = false) {
            const body = this.terminalBody();
            if (!body) {
                return;
            }

            const run = () => {
                body.scrollTop = body.scrollHeight;
            };

            if (immediate) {
                run();
                return;
            }

            requestAnimationFrame(run);
        },

        updateStatus(status) {
            this.markActivity();
            this.currentStatus = status;
            if (['running', 'failed', 'cancelled', 'rolled_back'].includes(status)) {
                this.finished = true;
            }
        },

        markActivity() {
            this.lastActivityAt = Date.now();
        },

        finishStream() {
            this.finished  = true;
            this.sseState  = 'connected';
            this.sseText   = 'completo';
            this.cleanup();

            // If websocket only delivered status/system lines, load persisted logs.
            if (this.streamedLogLines === 0) {
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
                        this.addLogLine(line, false);
                    }

                    this.scrollToBottom(true);
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

                const handleLogEvent = (event) => {
                    if (event?.line) {
                        this.addLogLine(event.line);
                    }
                };

                const handleStageEvent = (event) => {
                    if (event?.stage && event?.status) {
                        this.addLogLine('>>> ' + event.stage.toUpperCase() + ': ' + event.status);
                    }
                };

                const handleStatusEvent = (event) => {
                    if (event?.status) {
                        const statusKey = String(event.status) + '|' + String(event?.error || '');
                        if (this.lastStatusEvent === statusKey) {
                            return;
                        }
                        this.lastStatusEvent = statusKey;

                        this.updateStatus(event.status);
                        this.addSystemLine('>>> STATUS: ' + String(event.status).toUpperCase());

                        if (event?.error) {
                            this.addSystemLine('>>> ERRO: ' + event.error);
                        }

                        if (['running', 'failed', 'cancelled', 'rolled_back'].includes(event.status)) {
                            this.finishStream();
                        }
                    }
                };

                this.channel = window.Echo.private('deployment.' + this.deploymentId)
                    .listen('.BuildLogReceived', handleLogEvent)
                    .listen('.DeploymentStageChanged', handleStageEvent)
                    .listen('.DeploymentStatusChanged', handleStatusEvent);

                if (this.applicationId) {
                    this.appChannel = window.Echo.private('application.' + this.applicationId)
                        .listen('.BuildLogReceived', handleLogEvent)
                        .listen('.DeploymentStageChanged', handleStageEvent)
                        .listen('.DeploymentStatusChanged', handleStatusEvent);
                }

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
                    this.sseText = 'falha websocket, ativando sse';
                    this.connectSSE();
                }
            }, 500);
        },

        connectSSE() {
            if (!this.streamUrl || this.sse || this.finished) {
                return;
            }

            this.sse = new EventSource(this.streamUrl);

            this.sse.addEventListener('log', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data?.line) {
                        this.addLogLine(data.line);
                    }
                } catch (_) {
                    // ignore malformed frames
                }
            });

            this.sse.addEventListener('stage', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (data?.stage && data?.status) {
                        this.addLogLine('>>> ' + data.stage.toUpperCase() + ': ' + data.status);
                    }
                } catch (_) {
                    // ignore malformed frames
                }
            });

            this.sse.addEventListener('status', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (!data?.status) {
                        return;
                    }

                    const statusKey = String(data.status) + '|' + String(data?.error || '');
                    if (this.lastStatusEvent !== statusKey) {
                        this.lastStatusEvent = statusKey;
                        this.updateStatus(data.status);
                        this.addSystemLine('>>> STATUS: ' + String(data.status).toUpperCase());
                        if (data?.error) {
                            this.addSystemLine('>>> ERRO: ' + data.error);
                        }
                    }

                    if (['running', 'failed', 'cancelled', 'rolled_back'].includes(data.status)) {
                        this.finishStream();
                    }
                } catch (_) {
                    // ignore malformed frames
                }
            });

            this.sse.addEventListener('done', () => {
                this.finishStream();
            });

            this.sse.addEventListener('heartbeat', () => {
                this.markActivity();
            });

            this.sse.onopen = () => {
                this.markActivity();
                this.sseState = 'connected';
                this.sseText = 'sse backup ativo';
            };

            this.sse.onerror = () => {
                // EventSource auto-reconnects by default.
                this.sseState = 'error';
                this.sseText = 'sse reconectando';
            };
        },

        startRealtimeWatchdog() {
            if (this.watchdogTimer) {
                clearInterval(this.watchdogTimer);
            }

            this.watchdogTimer = setInterval(() => {
                if (this.finished) {
                    return;
                }

                const idleForMs = Date.now() - this.lastActivityAt;
                if (idleForMs > 10000) {
                    this.connectSSE();
                }
            }, 3000);
        },

        cleanup() {
            if (this.connectTimer) {
                clearInterval(this.connectTimer);
                this.connectTimer = null;
            }

            if (this.watchdogTimer) {
                clearInterval(this.watchdogTimer);
                this.watchdogTimer = null;
            }

            if (this.channel && window.Echo) {
                window.Echo.leave('deployment.' + this.deploymentId);
                this.channel = null;
            }

            if (this.appChannel && window.Echo && this.applicationId) {
                window.Echo.leave('application.' + this.applicationId);
                this.appChannel = null;
            }

            if (this.sse) {
                this.sse.close();
                this.sse = null;
            }
        },
    }));
});
