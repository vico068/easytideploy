---
description: Quebrar trabalho em tarefas pequenas e verificáveis com critérios de aceite e ordenação de dependências
---

# Workflow de Planejamento EasyDeploy

O usuário pediu ajuda com: $ARGUMENTS

---

## Etapa 1: Análise com Arquiteto

**OBRIGATÓRIO**: Antes de criar o plano, use o agente `arquiteto` para analisar:
- Estrutura atual do código relevante
- Impacto entre componentes (panel, orchestrator, agent)
- Dependências e ordem de execução
- Riscos e mitigações
- **Design System** — se houver UI, incluir especificações de design

Guarde o **input enviado ao arquiteto** e o **output retornado** para documentar no contexto.

---

## Design System EasyDeploy

**Quando o plano envolver UI** (Filament, Blade, componentes), inclua especificações de design:

### Filosofia
- **Profissional + Inovador** — clean sem ser genérico, futurista sem exagero
- **Animações sutis** — micro-interações que guiam sem distrair
- **Light mode first** — branco como padrão, com suporte a dark mode
- **Glassmorphism sutil** — cards e modais com backdrop-blur

### Paleta de Cores
```css
--brand-primary: #0d8bfa;      /* Azul EasyTI */
--brand-secondary: #06b6d4;    /* Cyan */
--brand-gradient: linear-gradient(135deg, #0d8bfa, #06b6d4);
```

### Padrões Obrigatórios
- **Cards**: `rounded-2xl`, borda sutil, hover com glow
- **Botões**: gradiente da marca, hover scale + shadow
- **Animações**: `transition-all duration-300`, entrada com fade/slide
- **Gráficos**: curvas suaves, tooltips estilizados, cores da paleta
- **Empty states**: ilustração + mensagem + CTA
- **Loading**: skeleton com pulse da marca

### Incluir no Plano
Para tarefas de UI, o plano deve conter:
1. **Mockup ASCII** do layout proposto
2. **Classes Tailwind** específicas para componentes novos
3. **Animações** a serem aplicadas (entrada, hover, transições)
4. **Cores** usando variáveis da paleta

---

## Etapa 2: Criar Slug da Tarefa

Baseado no $ARGUMENTS, crie um slug descritivo:
- Use kebab-case (palavras em minúsculo separadas por hífen)
- Máximo 5 palavras
- Exemplos: `metricas-focadas-usuario`, `auth-oauth-google`, `refactor-deploy-pipeline`

---

## Etapa 3: Gerar Arquivos de Planejamento

Crie 3 arquivos na pasta `tasks/`:

### 1. `tasks/context-{slug}.md` — Contexto Completo
Deve conter:
```markdown
# Contexto: {Título da Tarefa}

**Data:** YYYY-MM-DD
**Slug:** {slug}

## Input do Usuário
{O que o usuário pediu — $ARGUMENTS}

## Observações Iniciais
{Suas observações antes de chamar o arquiteto}

## Input para o Arquiteto
{Prompt exato enviado ao agente arquiteto}

## Output do Arquiteto
{Resposta completa do arquiteto — análise, arquivos identificados, decisões}

## Decisões Tomadas
{Resumo das decisões de design baseadas na análise}
```

### 2. `tasks/plan-{slug}.md` — Plano Detalhado
O plano técnico completo com:
- Contexto e problema
- Arquitetura da solução
- Grafo de dependências
- Fases de implementação
- Detalhamento de cada tarefa (critérios de aceite, verificação)
- Riscos e mitigações

### 3. `tasks/todo-{slug}.md` — Lista de Tarefas
Lista executável no formato:
```markdown
# TODO: {Título}

**Status**: 🟡 Aguardando Aprovação | **Slug:** {slug}

---

## FASE 1: Nome da Fase

- [ ] **T1** - Descrição da tarefa (Tamanho)
  - Arquivo: `path/to/file.php`
  - Critério: descrição do que valida conclusão
  - Verificar: comando ou ação de teste

- [ ] **CHECKPOINT 1**: Descrição ✅

## FASE 2: ...
```

---

## Etapa 4: Ler Código Relevante

1. Leia a spec existente (SPEC.md ou equivalente) e as seções relevantes do código
2. Identifique o grafo de dependências entre componentes
3. Entenda a estrutura atual antes de propor mudanças

---

## Etapa 5: Fatiar o Trabalho

**Regras de fatiamento para EasyDeploy:**
- Mudanças em proto (`agent.proto`) devem ser uma tarefa separada pois requerem `make proto`
- Mudanças no orchestrator e agent Go devem considerar rebuild Docker
- Mudanças no panel PHP que afetam jobs devem incluir restart do queue-worker
- Tarefas de banco (migrations) devem vir antes de código que depende dos novos campos
- Config Traefik deve ser verificada após mudanças na geração de rotas

**Tamanho das tarefas:**
- XS: ~15min, 1 arquivo (ex: adicionar campo no model)
- S: ~30min, 2-3 arquivos (ex: novo endpoint API)
- M: ~1h, 3-5 arquivos (ex: novo serviço com handler + cliente)
- L: >1h, 5+ arquivos (ex: nova feature cross-component) — deve ser quebrada em menores

**Fatiamento vertical:** Cada tarefa deve entregar um caminho completo (não camadas horizontais).

---

## Etapa 6: Apresentar para Aprovação

Após criar os 3 arquivos, apresente um resumo ao usuário:
- Slug gerado
- Quantidade de tarefas e fases
- Arquivos que serão criados/modificados
- Peça aprovação antes de iniciar a execução com `/dev`

---

## Arquivos Gerados

Ao final, você terá:
```
tasks/
├── context-{slug}.md   # Contexto completo (input/output arquiteto)
├── plan-{slug}.md      # Plano técnico detalhado
└── todo-{slug}.md      # Lista de tarefas executável
```

A skill `/dev` usará o `todo-{slug}.md` para executar e marcar tarefas como concluídas.
