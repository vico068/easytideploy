# Checklist de Deploy — EasyDeploy

Checklist pre-flight antes de cada deploy em produção. Use com o comando `/deploy` ou `/ship`.

## Antes do Commit

- [ ] `git diff` revisado — sem arquivos acidentais
- [ ] Sem secrets no diff (`.env`, API keys, senhas, tokens)
- [ ] Sem `docker-compose.override.yml` no commit
- [ ] Sem `vendor/`, `node_modules/`, `bin/` no commit
- [ ] Código Go compila: `cd orchestrator && go build ./...`
- [ ] Se alterou proto: structs idênticas no orchestrator e agent

## Antes do Deploy

### Se alterou Panel (PHP)
- [ ] Rotas novas registradas em `bootstrap/app.php`
- [ ] Validação de input em todos os endpoints novos
- [ ] `$fillable` atualizado nos Models afetados
- [ ] Filament Resources atualizados (forms, tables)

### Se alterou Orchestrator (Go)
- [ ] Queries SQL testadas com tipos pgx corretos
- [ ] Erros wrapped com `fmt.Errorf("contexto: %w", err)`
- [ ] Logs estruturados com zerolog
- [ ] Novos endpoints autenticados por API key

### Se alterou Agent (Go)
- [ ] Handler gRPC atualizado para campos novos
- [ ] Tags JSON idênticas ao orchestrator
- [ ] Será necessário rebuild no worker (177.85.77.175)

### Se alterou Banco (Migrations)
- [ ] Migration tem `down()` funcional
- [ ] Colunas novas são nullable ou tem DEFAULT
- [ ] Queries raw no Go atualizadas para colunas novas
- [ ] COALESCE em nullable int/string para pgx

### Se alterou Traefik (Config/Rotas)
- [ ] Config dinâmica em YAML (NÃO JSON)
- [ ] Struct tags `yaml:` corretas
- [ ] Middleware de segurança aplicado em novas rotas
- [ ] Arquivo gerado em `/etc/traefik/dynamic/`

### Se alterou docker-compose.yml
- [ ] Override no servidor (`docker-compose.override.yml`) não conflita
- [ ] Volumes named vs bind mount consistentes
- [ ] Variáveis de ambiente corretas (APP_URL, API_KEY, etc.)

## Durante o Deploy

- [ ] `git pull` no servidor deu certo (sem conflitos)
- [ ] Build completo sem erros
- [ ] Todos os containers `Up` após `docker compose up -d`
- [ ] Migration rodou sem erros (se aplicável)
- [ ] Cache Laravel limpo (se mudou config/rotas)

## Após o Deploy

- [ ] `docker compose ps` — todos containers running
- [ ] Health check do orchestrator: `curl localhost:8080/health`
- [ ] Health check do agent: `curl 177.85.77.175:9091/health`
- [ ] Logs sem erros: `docker compose logs --tail=20 orchestrator queue-worker`
- [ ] Testar funcionalidade alterada (deploy de teste, domínio, etc.)
- [ ] Domínios existentes continuam respondendo

## Rollback

Se algo quebrou:

```bash
# 1. Reverter commit
git revert HEAD
git push origin main

# 2. Pull e rebuild no servidor
ssh root@deploy.easyti.cloud "cd /opt/easydeploy && git pull && docker compose build && docker compose up -d"

# 3. Reverter migration (se aplicável)
ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec panel php artisan migrate:rollback"
```
