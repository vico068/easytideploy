---
description: Triagem sistemática de problemas em sistema distribuído — rastrear erros entre panel, orchestrator, agent e Traefik
---

Diagnosticar o problema: $ARGUMENTS

## Workflow de Debug Distribuído

Siga esta sequência para rastrear o problema no pipeline panel → orchestrator → agent → Traefik:

### 1. Reproduzir e Localizar

Primeiro, entenda onde o erro se manifesta:

```
Sintoma no Panel (UI/API)     → Começar pelo panel
Sintoma no deploy (build/run)  → Começar pelo orchestrator
Container não sobe             → Começar pelo agent
Domínio não responde           → Começar pelo Traefik
```

### 2. Coletar Evidências (NÃO adivinhar)

Para cada serviço envolvido, verificar os logs recentes:

```bash
# No servidor (deploy.easyti.cloud):
sshpass -p 'Nutertools@159' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose logs --tail=50 <servico>"

# Serviços: panel, queue-worker, orchestrator, traefik, postgres, redis
# No worker (177.85.77.175):
sshpass -p 'Nutertools@159' ssh root@177.85.77.175 "cd /opt/easydeploy && docker compose logs --tail=50 agent"
```

### 3. Rastrear o Fluxo

O pipeline EasyDeploy segue esta cadeia:

```
1. Panel (DeploymentService) → HTTP POST → Orchestrator (/api/v1/deployments)
2. Orchestrator → Redis queue (BuildJob)
3. Scheduler → git clone → docker build → registry push
4. Scheduler → gRPC CreateContainer → Agent
5. Agent → docker pull + run → Container rodando
6. Scheduler → saveContainer + updateTraefikConfig → Traefik config YAML
7. Scheduler → POST callback → Panel (atualiza status)
```

Para cada etapa, verificar:
- A requisição chegou no destino? (log de entrada)
- O processamento foi bem sucedido? (log de resultado)
- A resposta voltou ao chamador? (log de callback/resposta)

### 4. Verificar Estado no Banco

Consultar estado atual dos registros relevantes:

```bash
# No servidor de controle:
sshpass -p 'Nutertools@159' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec postgres psql -U easydeploy -c \"
  SELECT d.id, d.status, d.error_message, d.created_at
  FROM deployments d ORDER BY d.created_at DESC LIMIT 5;
\""
```

Queries úteis:
- Containers: `SELECT * FROM containers WHERE application_id = '...' ORDER BY created_at DESC;`
- Domínios: `SELECT * FROM domains WHERE application_id = '...' AND verified = true;`
- Apps: `SELECT id, name, health_check_path, traefik_config_updated_at FROM applications;`

### 5. Verificar Conectividade Inter-Serviços

```bash
# Panel → Orchestrator
curl -s http://localhost:8080/health

# Orchestrator → Agent (gRPC)
# Verificar se agent responde na porta 9091 (HTTP health)
curl -s http://177.85.77.175:9091/health

# Orchestrator → Registry
curl -s http://localhost:5000/v2/_catalog

# Traefik → Backend container
curl -s -o /dev/null -w "%{http_code}" http://localhost:<host_port>/
```

### 6. Problemas Comuns (referência rápida)

| Sintoma | Causa mais provável | Verificação |
|---------|---------------------|-------------|
| Job não processa | Queue worker parado ou com código velho | `docker compose logs queue-worker` + restart |
| Build falha | Buildpack incorreto ou Dockerfile ausente | Verificar `orchestrator/buildpacks/` |
| Container não sobe | Agent não alcança registry ou imagem não existe | Logs do agent + `curl registry` |
| Domínio 404 | Config Traefik não gerada ou container sem host_port | Verificar `/etc/traefik/dynamic/` |
| Domínio 503 | Health check falha ou container morto | Verificar health_check_path e container status |
| Callback falha | APP_URL errado no queue-worker ou API key inválida | Verificar env vars do queue-worker |
| pgx scan error | Tipo PostgreSQL incompatível (inet, nullable) | Usar host() e COALESCE na query |

### 7. Reportar

Após identificar o problema, documente:
1. **Sintoma**: o que o usuário vê
2. **Causa raiz**: onde e por que falha
3. **Correção**: o que precisa mudar e em quais arquivos
4. **Verificação**: como confirmar que o fix funciona
