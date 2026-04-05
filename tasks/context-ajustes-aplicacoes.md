# Contexto: Ajustes na Tela de Aplicações

**Data:** 2026-04-05
**Slug:** ajustes-aplicacoes

---

## Input do Usuario

### Listagem de Aplicacoes
- Ajustar visual da tabela com novo conceito de design
- Traduzir para portugues elementos do Filament que estao em ingles
- Botao de acoes em dropdown

### Tela de Criar Aplicacao
- Aplicar conceito novo de design
- Criar em forma de steps (wizard)
- Dominio inicial sugerido: `https://nomedoapp.apps.easyti.cloud` (nao `.easyti.cloud`)
- Quando criar o app, ja criar o dominio principal automaticamente

### Verificar Fluxo Completo
1. Deployment esta funcionando ao criar
2. Dominio ja e criado com SSL ativado
3. Load balance com multiplos containers + Traefik balanceado

### Bug: Commit Info no Deployment
- Commit SHA e mensagem nao estao aparecendo nos deployments

---

## Output do Arquiteto

### Causa raiz - Commit Info (Bug Critico)
O `scheduler.go` obtem SHA/message via `GetCommitInfo()` e armazena em `BuildResult.CommitSHA/CommitMsg`.
Porem `updateDeploymentStatus()` (linha 440) so atualiza `status` e `error_message`.
O `notifyPanel()` (linha 450) tambem nao envia commit info.

**Correcao em 3 pontos:**
1. `scheduler.go:updateDeploymentStatus()` - adicionar commit_sha e commit_message no UPDATE
2. `scheduler.go:notifyPanel()` - adicionar commit_sha e commit_message no payload
3. `panel/routes/api.php` callback - processar commit_sha e commit_message do payload

### Dominio Inconsistente
- Form panel: `.easyti.cloud`
- Model Application: `getDefaultDomainAttribute()` retorna `.easyti.cloud`
- Traefik config_generator: `{slug}.app.easyti.cloud` (singular)
- Config: `config/easydeploy.php` - `default_suffix => 'easyti.cloud'`

**Correto:** `.apps.easyti.cloud` (plural) em todos os lugares

### Arquivos Impactados

| Arquivo | Mudanca |
|---------|---------|
| `panel/app/Filament/Resources/ApplicationResource.php` | Tabela: dropdown acoes. Form: wizard steps. Dominio fix. |
| `panel/app/Filament/Resources/ApplicationResource/Pages/CreateApplication.php` | Wizard com HasWizard. Auto-criar dominio. |
| `panel/app/Models/Application.php` | Fix `getDefaultDomainAttribute` para `.apps.easyti.cloud` |
| `panel/config/easydeploy.php` | Fix `default_suffix` para `apps.easyti.cloud` |
| `orchestrator/internal/traefik/config_generator.go` | Fix default domain para `.apps.easyti.cloud` |
| `orchestrator/internal/scheduler/scheduler.go` | Fix: salvar commit info no DB + enviar no callback |
| `panel/routes/api.php` | Fix: processar commit info do callback |
| `panel/lang/pt_BR/` | Traducoes do Filament |
