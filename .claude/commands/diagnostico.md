---
description: Verificar saúde de todos os serviços — containers, domínios, Traefik, registry, banco
---

Rodar diagnóstico completo da plataforma EasyDeploy.

Contexto: $ARGUMENTS

## Diagnóstico Completo

Execute TODOS os checks abaixo em sequência. Reporte o resultado de cada um.

### 1. Containers no Servidor de Controle

```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose ps"
```

Todos estes devem estar `Up`:
- [ ] panel
- [ ] queue-worker
- [ ] orchestrator
- [ ] postgres
- [ ] redis
- [ ] traefik
- [ ] registry

### 2. Containers no Worker

```bash
sshpass -p 'EasyTI@2026' ssh root@177.85.77.175 "cd /opt/easydeploy && docker compose ps"
```

Deve estar `Up`:
- [ ] agent

### 3. Health Endpoints

```bash
# Orchestrator
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "curl -s http://localhost:8080/health"

# Agent
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "curl -s http://177.85.77.175:9091/health"

# Panel
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "curl -s -o /dev/null -w '%{http_code}' http://localhost:8000"

# Registry
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "curl -s http://localhost:5000/v2/_catalog"
```

### 4. Banco de Dados

```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec postgres psql -U easydeploy -c '
  SELECT
    (SELECT count(*) FROM applications) as apps,
    (SELECT count(*) FROM deployments) as deploys,
    (SELECT count(*) FROM deployments WHERE status = '\''running'\'') as running_deploys,
    (SELECT count(*) FROM deployments WHERE status = '\''failed'\'') as failed_deploys,
    (SELECT count(*) FROM containers WHERE status = '\''running'\'') as running_containers,
    (SELECT count(*) FROM domains WHERE verified = true) as verified_domains,
    (SELECT count(*) FROM servers) as servers;
'"
```

### 5. Traefik — Rotas Ativas

```bash
# Verificar configs dinâmicas geradas
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "ls -la /opt/easydeploy/proxy/dynamic/"

# Verificar se Traefik carregou as rotas (via API se habilitada)
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose logs --tail=10 traefik"
```

### 6. Domínios — Verificar Acesso

Para cada domínio verificado no banco:
```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec postgres psql -U easydeploy -c \"
  SELECT d.domain, a.name as app_name
  FROM domains d
  JOIN applications a ON d.application_id = a.id
  WHERE d.verified = true;
\""
```

Testar cada domínio:
```bash
curl -s -o /dev/null -w "%{http_code}" https://<dominio>/
```

### 7. Redis — Filas

```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec redis redis-cli LLEN build_queue"
```

### 8. Logs Recentes com Erros

```bash
# Verificar erros nos últimos logs
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose logs --tail=30 orchestrator queue-worker 2>&1 | grep -i 'error\|fatal\|panic'"
```

### 9. Disco e Memória

```bash
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "df -h / && echo '---' && free -h && echo '---' && docker system df"
```

### Formato de Saída

Apresente o resultado como:

```
## Diagnóstico EasyDeploy — [data]

| Check | Status | Detalhes |
|-------|--------|----------|
| Containers (controle) | OK/ERRO | X/Y rodando |
| Containers (worker) | OK/ERRO | agent status |
| Health endpoints | OK/ERRO | quais falharam |
| Banco de dados | OK/ERRO | stats |
| Traefik | OK/ERRO | N rotas ativas |
| Domínios | OK/ERRO | N/M respondendo |
| Redis | OK/ERRO | N jobs na fila |
| Erros recentes | OK/ERRO | resumo |
| Disco/Memória | OK/ALERTA | uso |

### Problemas Encontrados
1. [Problema] — [Ação recomendada]

### Tudo OK
Nenhum problema encontrado.
```
