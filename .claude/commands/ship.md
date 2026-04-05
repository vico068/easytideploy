---
description: Checklist pre-deploy e preparação para produção
---

Execute o checklist completo de pre-deploy para EasyDeploy:

### 1. Qualidade do Codigo
- [ ] Compilação limpa: `go build ./...` (orchestrator e agent)
- [ ] `go vet ./...` sem warnings
- [ ] Sem TODOs críticos no código
- [ ] Sem secrets hardcoded
- [ ] Sem `fmt.Println` de debug (usar zerolog)

### 2. Seguranca
- [ ] API keys protegidas (Bearer token, não query params)
- [ ] Endpoints internos autenticados (`/api/internal/*`)
- [ ] Docker socket acesso restrito
- [ ] Sem credentials nos logs
- [ ] Variáveis de ambiente sensíveis em `docker-compose.override.yml` (não no `.yml` versionado)

### 3. Banco de Dados
- [ ] Migrations rodam sem erro: `docker compose exec panel php artisan migrate`
- [ ] Queries com COALESCE para campos nullable
- [ ] Tipos PostgreSQL corretos (inet com `host()`, timestamps com timezone)
- [ ] Índices para queries frequentes

### 4. Infraestrutura
- [ ] `docker-compose.yml` atualizado
- [ ] Volumes montados corretamente (atenção ao override que pode mascarar)
- [ ] Config Traefik gerada em YAML (não JSON)
- [ ] Health checks configurados nos containers
- [ ] Portas expostas corretamente

### 5. Deploy
- [ ] `git status` limpo — apenas arquivos intencionais
- [ ] Commit com mensagem descritiva
- [ ] Push para origin main
- [ ] No servidor: `git pull && docker compose build <servico> && docker compose up -d <servico>`
- [ ] Se mudou panel: `docker compose restart panel queue-worker`
- [ ] Se mudou proto: `make proto` antes do build
- [ ] Verificar logs: `docker compose logs -f <servico>`

### 6. Verificacao Pos-Deploy
- [ ] Containers rodando: `docker compose ps`
- [ ] Endpoints respondendo (curl de teste)
- [ ] Traefik routeando domínios (verificar `/etc/traefik/dynamic/`)
- [ ] Deploy de teste funcional no painel

**Plano de rollback:**
```bash
# Se algo der errado:
git log --oneline -5                    # ver commits recentes
git revert <commit-hash>               # reverter mudança
git push origin main
# No servidor:
git pull && docker compose build && docker compose up -d
```
