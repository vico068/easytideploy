# EasyDeploy - Instruções para Claude

## Visão Geral

EasyDeploy é uma plataforma PaaS self-hosted estilo AWS Amplify / App Runner, desenvolvida para EasyTI Cloud. Roda em clusters Proxmox (ou qualquer VM Linux). Monorepo com 3 componentes principais.

## Arquitetura

```
panel (Laravel 11 + Filament 3)
  └─> OrchestratorClient.php  ──HTTP──>  orchestrator (Go/chi)
                                               |
                                         Redis queue
                                               |
                                         scheduler.go
                                         (build pipeline)
                                               |
                                    Docker socket (build image)
                                    Registry push (localhost:5000)
                                               |
                                          gRPC ──>  agent (Go)
                                                     |
                                               Docker daemon
                                               (worker VM)

Traefik lê /etc/traefik/dynamic/ ──> rota tráfego para containers
Panel recebe callback POST /api/internal/deployments/{id}/status
```

## Componentes

| Diretório | Stack | Porta | Papel |
|---|---|---|---|
| `panel/` | PHP 8.3, Laravel 11, Filament 3 | 8000 | Dashboard admin + API inbound |
| `orchestrator/` | Go 1.24 | 8080 | Control plane, build pipeline |
| `agent/` | Go 1.24 | 9090 gRPC / 9091 HTTP | Worker por VM |
| `proxy/` | Traefik v3 | 80/443 | Edge router + SSL |

## Fluxo de Deploy Completo

1. Usuário clica "Deploy" no Filament
2. `DeploymentService` cria registro `Deployment` e chama `OrchestratorClient->deploy()`
3. Orchestrator enfileira `BuildJob` no Redis com `callback_url` e `git_token`
4. `scheduler.go` processa o job: git clone → buildpack Dockerfile → docker build → push registry
5. Orchestrator envia gRPC `CreateContainer` para o agent no servidor worker
6. Agent puxa a imagem e sobe o container
7. Orchestrator escreve config Traefik no volume compartilhado
8. Orchestrator faz POST callback ao panel → panel atualiza status para `running` ou `failed`

## Convenções de Código

### Go (Orchestrator/Agent)
- Logging via `zerolog` — sempre usar campos estruturados: `log.Error().Str("field", val).Err(err).Msg("mensagem")`
- Erros com `fmt.Errorf("contexto: %w", err)` para wrapping
- Config via variáveis de ambiente, struct em `internal/config/`
- gRPC proto em `pkg/proto/agent.proto` — rodar `make proto` para regenerar

### PHP (Panel)
- Laravel 11: rotas de API em `routes/api.php` **devem** estar registradas em `bootstrap/app.php`
  ```php
  ->withRouting(
      web: __DIR__.'/../routes/web.php',
      api: __DIR__.'/../routes/api.php',
      ...
  )
  ```
- Queue workers são daemons de longa duração — **sempre restartar** após mudar código que roda em jobs
- `url()` usa `APP_URL` da config — verificar `.env` do serviço que gera as URLs
- Campos `environment` vazios para o orchestrator devem ser `new \stdClass()` (não `[]`), para serializar como `{}` no JSON
- Variáveis de ambiente de apps ficam em `EnvironmentVariable` model e são criptografadas
- Config customizada da plataforma em `config/easydeploy.php`

## Serviços no Servidor de Produção

**Servidor:** `177.85.77.175`
**Caminho:** `/opt/easydeploy/`
**Acesso:** `ssh root@177.85.77.175`

Serviços gerenciados por Docker Compose com override local:
- `/opt/easydeploy/docker-compose.yml` (versionado no git)
- `/opt/easydeploy/docker-compose.override.yml` (local, não versionado — contém segredos e overrides de produção)

Overrides ativos no servidor:
```yaml
# queue-worker: APP_URL correto para callbacks internos
# orchestrator: DATA_DIR=/app, user: root (para acesso ao Docker socket)
```

## Variáveis de Ambiente Importantes

| Variável | Onde | Valor em prod |
|---|---|---|
| `APP_URL` | panel, queue-worker | `http://panel:8000` |
| `API_KEY` | orchestrator, panel | chave compartilhada |
| `DATA_DIR` | orchestrator | `/app` (buildpacks em `/app/buildpacks/`) |
| `DOCKER_REGISTRY` | orchestrator, agent | `localhost:5000` ou IP do registry |

## Comandos Úteis

```bash
# Desenvolvimento local
make dev-all          # Sobe todo o stack Docker
make dev              # Infra no Docker + processos locais
make logs             # docker compose logs -f
make status           # docker compose ps

# Build
make build-orchestrator
make build-panel
make build-agent
make build            # todos

# Banco de dados
make migrate
make migrate-fresh    # reseta e aplica tudo com seed
make seed-demo

# Protobuf (regenerar código gRPC)
make proto            # requer protoc instalado

# Manutenção Laravel
make clean            # limpa caches
```

## Pontos de Atenção / Armadilhas Conhecidas

### Volumes Docker sobrepõem conteúdo da imagem
O volume `orchestrator_data` monta em `/var/lib/easydeploy`. Qualquer arquivo copiado para esse path no Dockerfile será mascarado pelo volume. Buildpacks ficam em `/app/buildpacks/` por isso.

### PHP Opcache
Após mudança de código no panel, **reiniciar o container** (`docker compose restart panel`) para o opcache ser invalidado.

### Queue Worker não recarrega código
`php artisan queue:work` é um daemon — não recarrega código entre jobs. Sempre reiniciar após deploys: `docker compose restart queue-worker`.

### gRPC Proto Types
Os tipos em `pkg/proto/agent_grpc.go` são manualmente escritos (não gerados por `protoc-gen-go`). Para mudanças no `agent.proto`, rodar `make proto` que usa `protoc` para gerar código correto e copiar para `agent/pkg/proto/`.

### Autenticação de Rotas Internas
O endpoint de callback `/api/internal/deployments/{id}/status` usa API Key (Bearer token), **não** `auth:sanctum`. Verificação feita inline na rota.

### APP_URL em Queue Workers
O serviço `queue-worker` é uma instância separada do panel (mesmo Dockerfile, comando diferente). Ele precisa da var `APP_URL` configurada corretamente para que `url()` gere URLs válidas para callbacks ao orchestrator.

## Estrutura de Arquivos Críticos

```
panel/
  app/Services/OrchestratorClient.php   # cliente HTTP para o orchestrator
  app/Services/DeploymentService.php    # orquestra o fluxo de deploy do lado panel
  bootstrap/app.php                     # REGISTRAR routes/api.php aqui
  routes/api.php                        # webhooks + callback interno
  config/easydeploy.php                 # config da plataforma

orchestrator/
  internal/scheduler/scheduler.go       # pipeline de build + notifyPanel()
  internal/api/handlers/deployments.go  # handler HTTP do deploy
  internal/queue/redis_queue.go         # structs BuildJob / HealthCheck
  pkg/proto/agent.proto                 # definição dos tipos gRPC
  pkg/proto/agent_grpc.go               # implementação Go do cliente/servidor gRPC
  buildpacks/                           # Dockerfiles por linguagem/versão

docker-compose.yml                      # stack completo
.env.example                            # referência de variáveis
Makefile                                # todos os comandos
```
