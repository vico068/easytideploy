# EasyDeploy PaaS

**Plataforma PaaS white-label para a EasyTI Cloud**

Uma plataforma completa estilo Amplify/App Runner self-hosted com containers distribuidos, gerenciamento de aplicacoes, deploys automatizados e monitoramento em tempo real.

---

## Indice

- [Visao Geral da Arquitetura](#visao-geral-da-arquitetura)
- [Componentes](#componentes)
- [Exemplo de Infraestrutura](#exemplo-de-infraestrutura-proxmox)
- [Requisitos](#requisitos)
- [Instalacao - Control Plane (VM)](#instalacao---control-plane-vm)
- [Instalacao - Agent (VM ou Host Proxmox)](#instalacao---agent-vm-ou-host-proxmox)
- [Instalacao Rapida do Agent (curl)](#instalacao-rapida-do-agent-via-curl)
- [Apos a Instalacao](#apos-a-instalacao)
- [Comandos do Makefile](#comandos-do-makefile)
- [Desenvolvimento Local](#desenvolvimento-local)
- [Estrutura do Monorepo](#estrutura-do-monorepo)
- [Backup e Restore](#backup-e-restore)
- [Desinstalacao](#desinstalacao)

---

## Visao Geral da Arquitetura

O EasyDeploy segue uma arquitetura **Control Plane + Agents**:

```
                         Internet
                            |
                     +--------------+
                     |   DNS *.app  |
                     +--------------+
                            |
  +------------------------------------------------------+
  |              VM Control Plane (emanoel)               |
  |                                                      |
  |   +----------+   +--------------+   +----------+    |
  |   | Traefik  |-->| Panel        |   | Redis    |    |
  |   | (Proxy)  |   | (Laravel)    |   | (Cache)  |    |
  |   +----------+   +--------------+   +----------+    |
  |        |          +--------------+   +----------+    |
  |        +--------->| Orchestrator |   | Postgres |    |
  |                   | (Go API)     |   | (Dados)  |    |
  |                   +--------------+   +----------+    |
  +-------------|------|------|---------------------------+
                |      |      |
           gRPC |  gRPC|  gRPC|
                |      |      |
  +-------------|------|------|---------------------------+
  |             v      v      v       Cluster Proxmox    |
  |                                                      |
  |  +-----------+  +-----------+  +-----------+         |
  |  | VM agape  |  | VM israel |  | VM yaweh  |         |
  |  |  Agent    |  |  Agent    |  |  Agent    |         |
  |  |  Docker   |  |  Docker   |  |  Docker   |         |
  |  | Containers|  | Containers|  | Containers|         |
  |  +-----------+  +-----------+  +-----------+         |
  +------------------------------------------------------+
```

**Fluxo:**
1. O usuario acessa o **Panel** (Filament) para gerenciar aplicacoes
2. O Panel envia comandos para o **Orchestrator** (API Go)
3. O Orchestrator distribui tarefas via **gRPC** para os **Agents** nos workers
4. Cada Agent gerencia containers Docker no servidor em que esta instalado
5. O **Traefik** roteia o trafego externo para os containers corretos via labels/config dinamica
6. Certificados SSL sao gerados automaticamente via **Let's Encrypt**

---

## Componentes

| Componente | Tecnologia | Descricao |
|------------|------------|-----------|
| **Panel** | Laravel 11 + Filament 3 | Dashboard administrativo: aplicacoes, servidores, deploys, logs, metricas |
| **Orchestrator** | Go 1.22 + chi router | API central: orquestra deploys, gerencia servidores, configura Traefik, metricas Prometheus |
| **Agent** | Go 1.22 + Docker SDK | Agente em cada worker: executa containers, reporta metricas, recebe comandos via gRPC |
| **Traefik** | Traefik v3 | Reverse proxy com SSL automatico, roteamento dinamico, metricas |
| **PostgreSQL** | PostgreSQL 16 | Banco de dados principal (UUIDs como chave primaria) |
| **Redis** | Redis 7 | Cache, sessoes, filas (Laravel queues) |
| **Registry** | Docker Registry | Registry local para imagens de build (opcional) |

---

## Exemplo de Infraestrutura (Proxmox)

```
+---------------------------------------------------------------------+
|                    Cluster Proxmox VE 9.x                           |
+---------------------------------------------------------------------+
|  Server: agape      |  Server: israel    |  Server: yaweh           |
|  Dell R620          |  IBM x3550 M4      |  Dell R620               |
|  VM: Docker Worker  |  VM: Docker Worker |  VM: Docker Worker       |
|  Agent instalado    |  Agent instalado   |  Agent instalado         |
+---------------------------------------------------------------------+
|  Server: emanoel                                                    |
|  VM: Control Plane (Panel + Orchestrator + Traefik + DB + Redis)    |
|                                                                     |
|  Storage: Ceph Squid (distribuido)                                  |
|  Rede: 10GbE (vmbr1 para Ceph)                                     |
+---------------------------------------------------------------------+
```

### Onde instalar cada componente?

| Componente | Onde instalar | Por que |
|------------|---------------|---------|
| **Control Plane** (Panel + Orchestrator + Traefik + PostgreSQL + Redis) | **VM dedicada** no Proxmox | Isola a gestao da plataforma. Recomendado uma VM Ubuntu/Debian com 4GB+ RAM e 20GB+ disco. Nao instale diretamente no host Proxmox. |
| **Agent** | **VM em cada node worker** do Proxmox | Cada VM worker roda o Agent + Docker. O agent gerencia containers das aplicacoes dos clientes. Use VMs Ubuntu/Debian com 4GB+ RAM. |

> **IMPORTANTE:** Nao instale o Control Plane nem os Agents diretamente no host Proxmox.
> Sempre use **VMs** (ou LXC privilegiados com Docker). Instalar diretamente no host
> Proxmox pode causar conflitos com o sistema de virtualizacao e comprometer a seguranca
> e estabilidade de todo o cluster.

### Configuracao recomendada das VMs

| VM | vCPU | RAM | Disco | SO | Funcao |
|----|------|-----|-------|-----|---------|
| `emanoel` (Control Plane) | 4 | 4-8 GB | 40 GB | Ubuntu 22.04+ | Panel, Orchestrator, Traefik, PostgreSQL, Redis |
| `agape` (Worker) | 4-8 | 8-16 GB | 50-100 GB | Ubuntu 22.04+ | Agent + containers de aplicacoes |
| `israel` (Worker) | 4-8 | 8-16 GB | 50-100 GB | Ubuntu 22.04+ | Agent + containers de aplicacoes |
| `yaweh` (Worker) | 4-8 | 8-16 GB | 50-100 GB | Ubuntu 22.04+ | Agent + containers de aplicacoes |

---

## Requisitos

### Control Plane (VM)

- Ubuntu 22.04+, Debian 12+, AlmaLinux/Rocky 9+ ou Amazon Linux 2023
- 2 GB RAM (minimo), 4 GB+ (recomendado)
- 10 GB disco livre (minimo), 40 GB+ (recomendado)
- Docker 24+ e Docker Compose v2 (instalados automaticamente pelo script)
- Portas livres: 80, 443, 5432, 6379, 8080, 8000
- Acesso a internet (para Docker, Let's Encrypt)
- DNS wildcard apontando para o IP da VM (ex: `*.easyti.cloud -> IP`)

### Agent (Worker VM)

- Ubuntu 22.04+, Debian 12+, AlmaLinux/Rocky 9+ ou Amazon Linux 2023
- 512 MB RAM (minimo), 4 GB+ (recomendado, depende das aplicacoes)
- 5 GB disco livre (minimo), 50 GB+ (recomendado)
- Docker 24+ (instalado automaticamente pelo script)
- Portas livres: 9090 (gRPC), 9091 (HTTP health/metrics), 80, 443
- Conectividade de rede com o Control Plane

---

## Instalacao - Control Plane (VM)

O instalador interativo configura **tudo**: PostgreSQL, Redis, Traefik, Orchestrator, Panel, SSL, firewall, backup automatico e usuario admin.

### Passo 1: Preparar a VM

Crie uma VM no Proxmox com a configuracao recomendada acima e instale o Ubuntu Server 22.04+.

### Passo 2: Configurar DNS

Aponte **todos** os registros DNS para o IP da VM do Control Plane.
O Traefik (que roda no Control Plane) funciona como load balancer e reverse proxy,
recebendo todo o trafego e roteando para os containers nos workers via rede interna.

```
deploy.easyti.cloud    -> IP da VM Control Plane
api.easyti.cloud       -> IP da VM Control Plane
traefik.easyti.cloud   -> IP da VM Control Plane
*.apps.easyti.cloud    -> IP da VM Control Plane  (Traefik roteia para os workers)
```

> **Como funciona:** O Traefik recebe as requisicoes na porta 80/443, identifica
> o dominio da aplicacao, e encaminha o trafego para o container correto no worker
> apropriado. Os workers nao precisam de IP publico - o Traefik se comunica com
> eles pela rede interna do cluster.

### Passo 3: Executar o instalador

```bash
# Baixar o projeto (ou clonar via git)
git clone https://github.com/vico068/easytideploy.git /opt/easydeploy
cd /opt/easydeploy

# Executar o instalador interativo
sudo bash scripts/install-control-plane.sh
```

### O que o instalador pergunta

O instalador vai solicitar interativamente cada parametro. Basta pressionar ENTER para aceitar o valor padrao:

```
--- Dominio e URLs ---
  Dominio principal (ex: easyti.cloud)
  Subdominio do painel (deploy.easyti.cloud)
  Subdominio da API (api.easyti.cloud)
  Subdominio do Traefik dashboard (traefik.easyti.cloud)

--- SSL / Let's Encrypt ---
  Habilitar SSL automatico
  Email para certificados
  Usar staging (para testes)

--- Banco de dados PostgreSQL ---
  Nome do banco
  Usuario
  Senha (auto-gerada se pressionar ENTER)
  Porta

--- Redis ---
  Senha (auto-gerada)
  Porta

--- Orchestrator ---
  API Key (auto-gerada, importante para conectar agents!)
  Porta

--- Painel Web ---
  Porta

--- Usuario Administrador ---
  Nome, email, senha

--- Traefik ---
  Porta do dashboard
  Nivel de log

--- Docker Registry ---
  Instalar registry local ou usar externo

--- Outros ---
  Fuso horario
  Backup automatico diario (horario, retencao)
  Configurar firewall (ufw)
  Carregar dados de demonstracao
```

### O que o instalador faz automaticamente

1. Verifica requisitos (OS, memoria, disco, portas, internet)
2. Instala dependencias do sistema (curl, git, jq, etc.)
3. Instala Docker e Docker Compose (se necessario)
4. Configura firewall (ufw)
5. Cria estrutura de diretorios em `/opt/easydeploy`
6. Gera arquivo `.env` com todas as configuracoes
7. Gera configuracao do Traefik (rotas, SSL, middlewares)
8. Gera `docker-compose.override.yml` de producao
9. Builda imagens Docker (Orchestrator, Panel)
10. Inicia servicos em ordem (PostgreSQL -> Redis -> Traefik -> Orchestrator -> Panel -> Queue Worker -> Scheduler)
11. Aguarda health checks de cada servico
12. Executa migracoes do banco de dados
13. Cria usuario administrador
14. Configura servico systemd (`easydeploy`) para auto-start
15. Configura backup automatico via cron
16. Configura rotacao de logs
17. Salva credenciais em `/opt/easydeploy/.credentials`
18. Executa verificacao final de saude

### Apos a instalacao do Control Plane

O instalador mostra um resumo com:
- URL do painel, email e senha do admin
- URL da API e API Key (necessaria para instalar agents!)
- Arquivo de credenciais: `/opt/easydeploy/.credentials`

```bash
# Verificar status dos servicos
cd /opt/easydeploy && docker compose ps

# Ver logs
cd /opt/easydeploy && docker compose logs -f

# Restart
systemctl restart easydeploy
```

---

## Instalacao - Agent (VM ou Host Proxmox)

O Agent deve ser instalado em **cada VM worker** que vai hospedar containers de aplicacoes.

### Passo 1: Preparar a VM worker

Crie uma VM no Proxmox com a configuracao recomendada (Ubuntu 22.04+, 4GB+ RAM, 50GB+ disco).

### Passo 2: Obter as credenciais do Control Plane

Voce vai precisar de:
- **URL do Orchestrator**: `https://api.easyti.cloud` (ou o que configurou)
- **API Key**: encontrada em `/opt/easydeploy/.credentials` na VM do Control Plane

### Passo 3: Executar o instalador

```bash
# No servidor worker, execute:
sudo bash scripts/install-agent.sh
```

Ou copie o script para o worker:
```bash
# A partir da VM do Control Plane:
scp /opt/easydeploy/scripts/install-agent.sh root@WORKER_IP:/tmp/
ssh root@WORKER_IP "bash /tmp/install-agent.sh"
```

### O que o instalador do Agent pergunta

```
--- Identificacao do Servidor ---
  ID unico do servidor (auto-gerado)
  Nome amigavel

--- Conexao com o Orchestrator ---
  URL do Orchestrator (https://api.seu-dominio.com)
  API Key do Orchestrator (OBRIGATORIA)

--- Rede ---
  Porta gRPC (9090)
  Porta HTTP health/metrics (9091)
  IP que o orchestrator usara para conectar

--- Docker ---
  Socket do Docker
  Maximo de containers neste servidor

--- Heartbeat ---
  Intervalo (30s)
  Timeout (10s)

--- Seguranca (TLS) ---
  Habilitar TLS para gRPC

--- Metricas ---
  Habilitar Prometheus
  Intervalo de coleta

--- Logging ---
  Nivel de log
  Formato JSON

--- Modo de Instalacao ---
  1) Docker (recomendado) - roda como container
  2) Binary - compila o binario Go
  3) Systemd - compila e roda como servico nativo

--- Outros ---
  Configurar firewall
  Fuso horario
```

### O que o instalador do Agent faz

1. Verifica requisitos (OS, memoria, disco, portas)
2. Detecta IP publico e privado do servidor
3. Instala dependencias e Docker
4. Instala Go (apenas se modo binary/systemd)
5. Configura firewall
6. Gera arquivo de configuracao `.env`
7. Instala o agent no modo escolhido (Docker, Binary ou Systemd)
8. Configura servico systemd para auto-start
9. Configura rotacao de logs
10. Cria o comando global `easydeploy-agent` para gerenciamento
11. Tenta registrar o agent no orchestrator automaticamente
12. Executa verificacao de saude

---

## Instalacao Rapida do Agent (via curl)

Para instalar o agent de forma nao-interativa (ideal para automacao):

```bash
curl -fsSL https://raw.githubusercontent.com/easytisolutions/easydeploy/main/scripts/install-agent.sh | \
  sudo \
  ORCHESTRATOR_URL=https://api.easyti.cloud \
  API_KEY=SUA_API_KEY_AQUI \
  bash
```

Parametros opcionais via variaveis de ambiente:

```bash
curl -fsSL .../install-agent.sh | \
  sudo \
  ORCHESTRATOR_URL=https://api.easyti.cloud \
  API_KEY=SUA_API_KEY \
  SERVER_ID=worker-01 \
  SERVER_NAME="Worker Agape" \
  MAX_CONTAINERS=100 \
  INSTALL_MODE=docker \
  LOG_LEVEL=info \
  bash
```

---

## Apos a Instalacao

### Acessar o Painel

1. Acesse `https://deploy.easyti.cloud` (ou o dominio configurado)
2. Faca login com o email e senha do admin criados na instalacao
3. Va em **Servidores** para verificar se os agents estao conectados (status: online)

### Gerenciar o Agent (no worker)

O instalador cria o comando `easydeploy-agent` com os seguintes subcomandos:

```bash
easydeploy-agent status       # Status do servico + health check
easydeploy-agent logs -f      # Ver logs em tempo real
easydeploy-agent health       # Health check JSON
easydeploy-agent metrics      # Metricas do servidor (CPU, RAM, disco)
easydeploy-agent containers   # Listar containers gerenciados
easydeploy-agent info         # Informacoes do agent
easydeploy-agent config       # Ver configuracao
easydeploy-agent restart      # Reiniciar agent
easydeploy-agent update       # Atualizar para nova versao
easydeploy-agent uninstall    # Desinstalar completamente
```

### Adicionar mais Workers

Repita a [instalacao do Agent](#instalacao---agent-vm-ou-host-proxmox) em cada nova VM worker. O agent se registra automaticamente no orchestrator via heartbeat.

---

## Comandos do Makefile

### Desenvolvimento
```bash
make dev              # Sobe infra local + painel + orchestrator + agent
make dev-all          # Sobe tudo via docker compose
make dev-down         # Para tudo
```

### Build
```bash
make build            # Builda todas as imagens Docker
make build-panel      # Builda so o panel
make build-orchestrator
make build-agent
```

### Testes
```bash
make test             # Roda todos os testes
make test-panel       # Testes do Laravel
make test-orchestrator
make test-agent
```

### Database
```bash
make migrate          # Rodar migracoes
make migrate-fresh    # Reset + seed
make seed             # Rodar seeders
make seed-demo        # Carregar dados demo
```

### Producao
```bash
make setup            # Instalar control plane (interativo)
make setup-agent      # Instalar agent (interativo)
make uninstall        # Desinstalar control plane
```

### Backup
```bash
make backup           # Backup do banco
make restore FILE=./backups/arquivo.dump
make backup-list      # Listar backups
```

### Monitoramento
```bash
make status           # docker compose ps
make logs             # docker compose logs -f
```

---

## Desenvolvimento Local

Para desenvolver localmente sem Docker:

```bash
# 1. Instalar dependencias
make install

# 2. Subir infra (PostgreSQL + Redis + Traefik via Docker)
make dev-infra

# 3. Copiar e configurar .env
cp .env.example .env

# 4. Rodar migracoes
cd panel && php artisan migrate --seed

# 5. Iniciar tudo
make dev
```

Endpoints de desenvolvimento:
- Panel: `http://localhost:8000`
- Orchestrator API: `http://localhost:8080`
- Agent gRPC: `localhost:9090`
- Agent HTTP: `http://localhost:9091`

---

## Estrutura do Monorepo

```
easydeploy/
├── panel/                  # Laravel 11 + Filament 3 (Dashboard)
│   ├── app/
│   │   ├── Filament/       # Pages, Resources, Widgets
│   │   ├── Models/         # Eloquent models (UUID)
│   │   ├── Observers/      # Activity logging
│   │   └── Services/       # LogStream, Deploy, etc.
│   ├── database/
│   │   ├── migrations/     # PostgreSQL migrations
│   │   └── seeders/        # DemoSeeder incluido
│   ├── Dockerfile          # PHP 8.3-FPM + Nginx + Supervisor
│   └── docker/             # php.ini, nginx.conf, supervisord.conf
│
├── orchestrator/           # Go 1.22 - API Central
│   ├── cmd/orchestrator/   # Entrypoint main.go
│   ├── internal/
│   │   ├── api/            # chi router, middlewares, handlers
│   │   ├── alerting/       # AlertManager, rules, channels
│   │   ├── config/         # Configuracao via env
│   │   ├── database/       # PostgreSQL + SQLC queries
│   │   ├── deploy/         # Pipeline de deploy
│   │   ├── logging/        # Structured logging
│   │   ├── metrics/        # Prometheus metrics
│   │   ├── scheduler/      # Cron-like scheduling
│   │   └── traefik/        # Geracao de config dinamica
│   ├── pkg/proto/          # Protobuf definitions (gRPC)
│   └── Dockerfile          # Multi-stage Go build
│
├── agent/                  # Go 1.22 - Agent nos Workers
│   ├── cmd/agent/          # Entrypoint main.go
│   ├── internal/
│   │   ├── config/         # Configuracao via env
│   │   ├── docker/         # Docker SDK (containers, builds, logs)
│   │   ├── grpc/           # gRPC server
│   │   ├── health/         # Heartbeat reporter
│   │   └── metrics/        # Prometheus metrics do host
│   ├── pkg/proto/          # Protobuf (shared with orchestrator)
│   └── Dockerfile          # Multi-stage Go build
│
├── proxy/                  # Traefik v3
│   ├── traefik.yml         # Config estatica
│   └── dynamic/            # Configuracoes dinamicas
│
├── docker/                 # Buildpacks por linguagem
│   ├── golang/
│   ├── nodejs/
│   ├── php/
│   ├── python/
│   └── static/
│
├── scripts/                # Scripts de instalacao
│   ├── install-control-plane.sh    # Instalador completo do control plane
│   ├── install-agent.sh            # Instalador completo do agent
│   └── uninstall-control-plane.sh  # Desinstalador
│
├── docker-compose.yml      # Stack completa (dev)
├── docker-compose.prod.yml # Override de producao
├── .env.example            # Variaveis de ambiente exemplo
└── Makefile                # Comandos uteis
```

---

## Backup e Restore

### Backup manual

```bash
cd /opt/easydeploy
docker exec easydeploy-postgres pg_dump -U easydeploy -Fc easydeploy > backup.dump
```

### Backup automatico

Se habilitado durante a instalacao, um cron job roda diariamente:
- Faz dump do PostgreSQL
- Copia `.env` e certificados ACME
- Remove backups antigos apos o periodo de retencao

```bash
# Backup manual usando o script
/opt/easydeploy/scripts/backup.sh

# Listar backups
ls -lh /opt/easydeploy/backups/
```

### Restore

```bash
cd /opt/easydeploy
docker exec -i easydeploy-postgres pg_restore \
  -U easydeploy -d easydeploy --clean --if-exists < backup.dump
```

---

## Desinstalacao

### Control Plane

```bash
sudo bash /opt/easydeploy/scripts/uninstall-control-plane.sh
```

O script pergunta o que remover:
- Volumes Docker (banco de dados, Redis)
- Backups
- Docker Engine

### Agent

```bash
easydeploy-agent uninstall
```

Ou manualmente:
```bash
systemctl stop easydeploy-agent
systemctl disable easydeploy-agent
rm -f /etc/systemd/system/easydeploy-agent.service
cd /opt/easydeploy-agent && docker compose down -v
rm -rf /opt/easydeploy-agent
rm -f /usr/local/bin/easydeploy-agent
```

---

## Portas Utilizadas

| Porta | Servico | Funcao |
|-------|---------|--------|
| 80 | Traefik | HTTP (redireciona para HTTPS) |
| 443 | Traefik | HTTPS (SSL termination) |
| 5432 | PostgreSQL | Banco de dados (apenas interno por padrao) |
| 6379 | Redis | Cache/Filas (apenas interno por padrao) |
| 8000 | Panel | Dashboard web (via Traefik em producao) |
| 8080 | Orchestrator | API REST (via Traefik em producao) |
| 8081 | Traefik | Dashboard do Traefik |
| 9090 | Agent | gRPC (comunicacao orchestrator <-> agent) |
| 9091 | Agent | HTTP health checks e metricas Prometheus |

---

## Seguranca

- Rate limiting por IP no Orchestrator (120 req/min)
- Security headers (HSTS, X-Content-Type-Options, X-Frame-Options)
- Limite de tamanho de request (10MB)
- Containers com `no-new-privileges`, PID limits, log rotation
- Comunicacao orchestrator-agent via gRPC (TLS opcional)
- Autenticacao via Bearer token na API
- Senhas geradas automaticamente pelo instalador
- Firewall configurado automaticamente (ufw)
- Auditoria de atividades (Activity Log)

---

## Licenca

Proprietario - EasyTI Solutions
