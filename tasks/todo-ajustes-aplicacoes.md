# TODO: Ajustes na Tela de Aplicacoes

**Status**: ✅ Concluido | **Data**: 2026-04-05

---

## FASE 1: Listagem de Aplicacoes

- [x] **T1** - Acoes da tabela em dropdown (ActionGroup)
- [x] **T2** - Traducao do Filament para pt_BR (locale + enums)
- [x] **T3** - Visual da tabela melhorado (icones, layout compacto)

- [x] **CHECKPOINT 1**: Tabela com dropdown, traduzida e estilizada

---

## FASE 2: Dominio Padrao

- [x] **T4** - Fix `config/easydeploy.php`: default_suffix -> `apps.easyti.cloud`
- [x] **T5** - Fix `Application::getDefaultDomainAttribute` -> `.apps.easyti.cloud`
- [x] **T6** - Fix form slug suffix -> `.apps.easyti.cloud`
- [x] **T7** - Fix `config_generator.go` default domain -> `.apps.easyti.cloud`

- [x] **CHECKPOINT 2**: Dominio padrao consistente em todos os pontos

---

## FASE 3: Wizard de Criacao

- [x] **T8** - Converter CreateApplication para wizard (Wizard Steps)
- [x] **T9** - Separar form em 4 steps logicos
- [x] **T10** - Auto-criar dominio primario ao criar aplicacao (afterCreate)

- [x] **CHECKPOINT 3**: Wizard funcional com dominio auto-criado

---

## FASE 4: Fix Commit Info no Deployment

- [x] **T11** - scheduler.go: novo metodo saveCommitInfo() + chamada apos build
- [x] **T12** - scheduler.go: enviar commit_sha e commit_message no notifyPanel()
- [x] **T13** - api.php callback: processar commit_sha e commit_message

- [x] **CHECKPOINT 4**: Deployments mostram commit SHA e mensagem

---

## FASE 5: Verificacao e Correcoes

- [x] **T14** - BUG CRITICO: containers antigos nao removidos no deploy (cleanupOldContainers)
- [x] **T15** - Compilacao Go verificada OK
- [x] **T16** - Sintaxe PHP verificada OK

- [x] **CHECKPOINT FINAL**: Fluxo completo verificado e corrigido

---

## Problemas Identificados (para futuro)

1. **root_directory** nao e enviado ao orchestrator no OrchestratorClient::deploy()
2. **git_token** ausente no Retry do orchestrator (deployments.go)
3. **CertManager** do orchestrator nao inicializado (ssl_status fica pendente no DB)
4. **Deploy nao e automatico** ao criar app (usuario precisa clicar Deploy manualmente)
