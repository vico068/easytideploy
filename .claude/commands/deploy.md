---
description: Commit, push e deploy automático nos servidores de produção
---

Fazer deploy das mudanças atuais para produção.

Contexto adicional do usuário: $ARGUMENTS

## Workflow de Deploy

### 1. Verificar o que mudou

```bash
git status
git diff --stat
```

Analisar quais componentes foram afetados:
- `panel/` → PHP (restart containers, sem rebuild)
- `orchestrator/` → Go (requer rebuild)
- `agent/` → Go (requer rebuild no worker)
- `docker-compose.yml` → Infraestrutura (rebuild + up -d)
- `proxy/` → Traefik (restart traefik)
- Migrations → Requer migrate no servidor

### 2. Commitar

Commite apenas arquivos relevantes (NUNCA `.env`, `docker-compose.override.yml`, `*.dump`):

```bash
git add <arquivos específicos>
git commit -m "tipo: descrição do que foi feito"
git push origin main
```

### 3. Deploy no Servidor de Controle (deploy.easyti.cloud)

```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && git pull origin main"
```

Depois, conforme o que mudou:

**Apenas Panel (PHP):**
```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose restart panel queue-worker"
```

**Orchestrator (Go — requer rebuild):**
```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose build orchestrator && docker compose up -d orchestrator"
```

**Tudo:**
```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose build && docker compose up -d"
```

### 4. Deploy no Worker (177.85.77.175) — se agent mudou

```bash
sshpass -p 'EasyTI@2026' ssh root@177.85.77.175 "cd /opt/easydeploy && git pull origin main && docker compose build agent && docker compose up -d agent"
```

### 5. Pós-deploy

Se houver migrations:
```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec panel php artisan migrate --force"
```

Limpar cache Laravel se mudou config/rotas:
```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec panel php artisan config:clear && docker compose exec panel php artisan route:clear && docker compose exec panel php artisan cache:clear"
```

### 6. Verificar

```bash
# Checar se todos os containers estão rodando
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose ps"

# Verificar logs por erros
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose logs --tail=20 orchestrator queue-worker panel"
```

### Regras

- SEMPRE verificar `git diff` antes de commitar
- NUNCA fazer push de secrets ou override files
- SEMPRE verificar logs após deploy
- Se algo quebrar, reverter com `git revert HEAD` + redeploy
