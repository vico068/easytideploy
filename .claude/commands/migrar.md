---
description: Criar e aplicar migrações de banco — sincronizar schema entre Laravel (panel) e queries Go (orchestrator)
---

Criar ou aplicar migração de banco de dados.

Contexto: $ARGUMENTS

## Workflow de Migração

### 1. Entender o Impacto

Antes de criar qualquer migração, mapear **quem acessa** a tabela/coluna:

```
Panel (Laravel)       → Eloquent models em app/Models/
Orchestrator (Go)     → Queries raw SQL em internal/scheduler/ e internal/traefik/
Config Generator      → Queries em internal/traefik/config_generator.go
Agent (Go)            → Raramente acessa banco diretamente
```

**REGRA**: Se a migração afeta uma tabela usada pelo orchestrator, VERIFICAR todas as queries raw nesse componente. O Eloquent do Laravel se adapta automaticamente; queries raw em Go NÃO.

### 2. Criar a Migração (Laravel)

```bash
# Gerar arquivo de migração
docker compose exec panel php artisan make:migration <nome_descritivo>
# Exemplo: add_host_port_to_containers_table
```

Convenções:
- Nome descritivo: `add_<coluna>_to_<tabela>_table`, `create_<tabela>_table`, `update_<coluna>_in_<tabela>_table`
- Sempre incluir `down()` para rollback
- Usar tipos corretos (inet para IP, jsonb para JSON, etc.)
- Campos nullable quando o dado pode não existir

### 3. Verificar Queries Go Impactadas

Buscar todas as referências à tabela alterada no orchestrator:

```bash
grep -r "<nome_tabela>" orchestrator/internal/ --include="*.go"
```

Para cada query encontrada:
- A query seleciona a coluna nova? Precisa ser adicionada?
- A query insere na tabela? Precisa incluir a coluna nova?
- Tipos PostgreSQL especiais (inet, jsonb, array) precisam de tratamento no pgx:
  - `inet` → usar `host(coluna)` para obter string sem máscara CIDR
  - `nullable int` → usar `COALESCE(coluna, 0)` para evitar erro de scan
  - `nullable string` → usar `COALESCE(coluna, '')` ou scan para `*string`

### 4. Atualizar Model Laravel (se necessário)

- Adicionar coluna ao `$fillable` do Model
- Adicionar cast se necessário (`$casts`)
- Atualizar Filament Resource se a coluna aparece em forms/tables

### 5. Aplicar Localmente

```bash
make migrate
# ou
docker compose exec panel php artisan migrate
```

### 6. Aplicar em Produção

```bash
# Após push e pull no servidor:
sshpass -p 'Nutertools@159' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec panel php artisan migrate --force"
```

### 7. Verificar

```bash
# Confirmar que a migração rodou
sshpass -p 'Nutertools@159' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && docker compose exec postgres psql -U easydeploy -c '\d <tabela>'"
```

### Armadilhas

| Armadilha | Prevenção |
|-----------|-----------|
| Query Go quebra com coluna nova NOT NULL | Sempre usar DEFAULT ou nullable para colunas novas |
| pgx não scaneia inet em string | Usar `host(coluna)` no SELECT |
| pgx não scaneia null int | Usar `COALESCE(coluna, 0)` |
| Eloquent cache de schema | `php artisan config:clear` após migração |
| Orchestrator não recarrega | Rebuild e restart necessário após mudar queries |
