# Workflow de Desenvolvimento EasyDeploy

Contexto do projeto:
- **Monorepo**: panel (Laravel 11 + Filament), orchestrator (Go), agent (Go)
- **Branch principal**: `main` — deploys são feitos via git pull no servidor
- **Docker Compose**: `docker-compose.yml` + `docker-compose.override.yml` (local, não versionado)

## Servidores

| Servidor | Host | Papel | Stack |
|---|---|---|---|
| **Controle** | `deploy.easyti.cloud` | Panel, Orchestrator, Queue Workers | `/opt/easydeploy/` |
| **Worker** | `177.85.77.175` | Agent (executa containers) | `/opt/easydeploy-agent/` |

Acesso: usuário `root`, senha `Nutertools@159` — usar `sshpass`:
```bash
# Servidor de controle (panel + orchestrator + workers)
sshpass -p 'Nutertools@159' ssh root@deploy.easyti.cloud

# Servidor worker (agent)
sshpass -p 'Nutertools@159' ssh root@177.85.77.175
```

O usuário pediu ajuda com: $ARGUMENTS

---

## Carregar Contexto do Planejamento

**OBRIGATÓRIO**: Antes de executar qualquer tarefa, carregue os 3 arquivos de planejamento:

```
tasks/
├── context-{slug}.md   # Contexto: input do usuário, output do arquiteto, decisões
├── plan-{slug}.md      # Plano técnico detalhado
└── todo-{slug}.md      # Lista de tarefas executável
```

### Fluxo de Inicialização

1. **Listar arquivos em `tasks/`** para identificar o slug da tarefa
2. **Ler `context-{slug}.md`** — contém:
   - Input original do usuário
   - Observações iniciais
   - Output completo do arquiteto (análise, arquivos identificados, decisões)
   - Decisões de design tomadas
3. **Ler `plan-{slug}.md`** — contém:
   - Detalhamento técnico de cada tarefa
   - Critérios de aceite
   - Comandos de verificação
   - Riscos e mitigações
4. **Ler `todo-{slug}.md`** — contém:
   - Lista de tarefas com checkboxes
   - Status atual de execução

### Usar o Contexto Durante a Execução

- **Sempre consulte o contexto** quando tiver dúvidas sobre implementação
- O output do arquiteto contém decisões importantes (ex: queries, estrutura, padrões)
- O plano contém critérios de aceite que devem ser seguidos
- Se uma tarefa não estiver clara, releia o contexto antes de perguntar ao usuário

---

## Rastreamento de Tarefas (TODO)

**IMPORTANTE**: Mantenha o `tasks/todo-{slug}.md` sempre atualizado:

1. **Antes de começar cada tarefa**: Identifique a próxima `- [ ]` pendente
2. **Ao completar uma tarefa**: Marque imediatamente `- [x]`
3. **Ao completar um checkpoint**: Verifique critérios e marque `✅`

### Formato de marcação

```markdown
# Antes (pendente)
- [ ] **T1** - Descrição da tarefa

# Depois (concluída)
- [x] **T1** - Descrição da tarefa ✅
```

### Fluxo de execução

1. Ler os 3 arquivos de planejamento (context, plan, todo)
2. Identificar a próxima tarefa `- [ ]` não marcada
3. Consultar o **contexto** e o **plano** para entender os detalhes
4. Executar a tarefa seguindo os critérios de aceite do plano
5. **Imediatamente após concluir**: Editar `todo-{slug}.md` para marcar `- [x]`
6. Verificar checkpoint se todas as tarefas da fase estiverem completas
7. Repetir até todas as tarefas estarem concluídas

### Atualizar status do TODO

Quando todas as tarefas de uma fase estiverem completas:
- Marque o checkpoint da fase como `✅`
- Atualize o status no topo: `🟡 Aguardando Aprovação` → `🟢 Em Execução` → `✅ Concluído`

---

## Fluxo padrão de desenvolvimento

Siga esta sequência sempre que implementar uma mudança:

### 1. Implementar localmente

Faça as alterações necessárias nos arquivos. Consulte o `CLAUDE.md` para convenções.

**Dicas por componente:**
- **Panel (PHP)**: Após mudanças em jobs/serviços, lembrar que `queue-worker` é daemon — precisará restart no servidor
- **Orchestrator/Agent (Go)**: Mudanças requerem rebuild da imagem Docker
- **Proto**: Se mudar `pkg/proto/agent.proto`, rodar `make proto` antes de commitar

---

## Design System EasyDeploy

**Quando implementar UI** (Filament, Blade, componentes), siga rigorosamente:

### Filosofia
- **Profissional + Futurista** — clean mas inovador, não genérico
- **Animações sutis** — micro-interações em hover, entrada, transições
- **Light mode first** — branco como padrão, com suporte completo a dark mode
- **Glassmorphism sutil** — backdrop-blur em cards e modais

### Paleta de Cores (USAR SEMPRE)
```css
--brand-primary: #0d8bfa;      /* Azul EasyTI */
--brand-secondary: #06b6d4;    /* Cyan */
--brand-gradient: linear-gradient(135deg, #0d8bfa, #06b6d4);

/* Tailwind equivalentes */
bg-sky-500        /* brand-primary */
bg-cyan-500       /* brand-secondary */
from-sky-500 to-cyan-500  /* gradiente */
```

### Padrões de Componentes

**Cards:**
```html
<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-white/10
            p-6 hover:bg-slate-800/70 hover:border-sky-500/30
            hover:shadow-lg hover:shadow-sky-500/10
            transition-all duration-300">
```

**Botões primários:**
```html
<button class="bg-gradient-to-r from-sky-500 to-cyan-500
               text-white font-medium px-6 py-2.5 rounded-xl
               hover:shadow-lg hover:shadow-sky-500/25
               hover:scale-[1.02] transition-all duration-200">
```

**Stats cards com glow:**
```html
<div class="group relative overflow-hidden rounded-2xl bg-slate-800/50 p-6
            border border-white/5 hover:border-sky-500/30 transition-all duration-300">
    <div class="absolute inset-0 bg-gradient-to-br from-sky-500/0 to-cyan-500/0
                group-hover:from-sky-500/5 group-hover:to-cyan-500/5 transition-all duration-500"></div>
    <!-- conteúdo -->
</div>
```

**Seletor elegante:**
```html
<select class="bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5
               text-white focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20
               transition-all duration-200">
```

**Terminal de logs:**
```html
<div class="bg-slate-900 rounded-xl border border-slate-700 overflow-hidden">
    <div class="bg-slate-800/50 px-4 py-2 border-b border-slate-700 flex items-center gap-2">
        <div class="flex gap-1.5">
            <div class="w-3 h-3 rounded-full bg-red-500"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
            <div class="w-3 h-3 rounded-full bg-green-500"></div>
        </div>
        <span class="text-slate-400 text-sm">Logs</span>
    </div>
    <div class="p-4 font-mono text-sm text-slate-300 max-h-96 overflow-y-auto">
        <!-- logs -->
    </div>
</div>
```

### Animações Obrigatórias

```css
/* Transições padrão */
transition-all duration-200  /* Hover em botões */
transition-all duration-300  /* Hover em cards */
transition-all duration-500  /* Glow effects */

/* Entrada de elementos (Alpine.js) */
x-transition:enter="transition ease-out duration-300"
x-transition:enter-start="opacity-0 -translate-y-2"
x-transition:enter-end="opacity-100 translate-y-0"

/* Hover scale */
hover:scale-[1.02]  /* Botões */
hover:scale-[1.01]  /* Cards */
```

### Gráficos (Chart.js)

```javascript
// Cores
const brandColors = {
    primary: '#0d8bfa',
    secondary: '#06b6d4',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
};

// Config padrão
{
    animation: { duration: 750, easing: 'easeOutQuart' },
    elements: {
        line: { tension: 0.4, borderWidth: 2 },
        point: { radius: 0, hoverRadius: 6 }
    },
    plugins: {
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            borderColor: 'rgba(255,255,255,0.1)',
            cornerRadius: 8,
        }
    }
}
```

### Regras de UI

1. **NUNCA** usar `rounded-sm` ou `rounded-md` — sempre `rounded-xl` ou `rounded-2xl`
2. **NUNCA** usar cores cinzas puras — sempre slate (bg-slate-800, text-slate-400)
3. **SEMPRE** adicionar hover state em elementos clicáveis
4. **SEMPRE** usar gradiente da marca em CTAs primários
5. **SEMPRE** incluir transições em mudanças de estado
6. **SEMPRE** usar shadows com cor (shadow-sky-500/20) ao invés de cinza

---

### 2. Verificar o que mudou

```bash
git diff --stat
git status
```

### 3. Commitar e fazer push

Commite apenas os arquivos relevantes (nunca `.env`, `docker-compose.override.yml`):

```bash
git add <arquivos específicos>
git commit -m "tipo: descrição curta do que foi feito"
git push origin main
```

Tipos de commit: `feat`, `fix`, `refactor`, `chore`, `docs`

### 4. Conectar ao servidor de controle

```bash
sshpass -p 'Nutertools@159' ssh root@deploy.easyti.cloud
cd /opt/easydeploy
```

### 5. Puxar as mudanças

```bash
git pull origin main
```

### 6. Reconstruir e restartar conforme o que mudou

**Apenas panel (PHP — sem rebuild de imagem):**
```bash
docker compose restart panel queue-worker scheduler
```

**Orchestrator (requer rebuild):**
```bash
docker compose build orchestrator
docker compose up -d orchestrator
```

**Agent (roda no servidor worker 177.85.77.175 — requer rebuild lá):**
```bash
# No servidor de controle: apenas push do código já basta
# Conectar no worker e rebuildar:
sshpass -p 'Nutertools@159' ssh root@177.85.77.175 "cd /opt/easydeploy && git pull origin main && docker compose build agent && docker compose up -d agent"
```

**Panel com mudança de dependência (composer/npm):**
```bash
docker compose build panel
docker compose up -d panel queue-worker scheduler
```

**Tudo (mudanças que afetam múltiplos serviços):**
```bash
docker compose build
docker compose up -d
```

### 7. Limpar cache Laravel (quando necessário)

Após mudanças em config, rotas ou código PHP:
```bash
docker compose exec panel php artisan config:clear
docker compose exec panel php artisan cache:clear
docker compose exec panel php artisan route:clear
```

Ou usar o atalho do Makefile:
```bash
make clean
```

### 8. Verificar logs

```bash
# Acompanhar logs em tempo real de todos os serviços
docker compose logs -f

# Serviço específico
docker compose logs -f orchestrator
docker compose logs -f queue-worker
docker compose logs -f panel

# Checar status dos containers
docker compose ps
```

### 9. Testar o fluxo de deploy

Acesse `http://deploy.easyti.cloud` (ou o domínio configurado) e dispare um deploy de teste.

Acompanhar o pipeline completo:
```bash
# Terminal 1 — orchestrator (build + callback)
docker compose logs -f orchestrator

# Terminal 2 — queue worker (processamento do job Laravel)
docker compose logs -f queue-worker
```

---

## Referência rápida de troubleshooting

| Sintoma | Causa provável | Solução |
|---|---|---|
| Rota retorna 404 | Opcache desatualizado ou rota não registrada em `bootstrap/app.php` | `docker compose restart panel` |
| Job não processa | Queue worker não restarted após mudança de código | `docker compose restart queue-worker` |
| Callback URL `http://localhost/...` | `APP_URL` não definido no queue-worker | Verificar `docker-compose.override.yml` |
| `buildpack not found` | Buildpack não existe em `/app/buildpacks/` | Verificar `orchestrator/buildpacks/` e rebuild |
| `cannot connect to Docker daemon` | Socket não montado | Verificar volume `/var/run/docker.sock` em `docker-compose.yml` |
| `proto: failed to marshal` | Tipos proto sem `proto.Message` | Codec JSON já resolve — verificar `pkg/proto/codec.go` e `agent/internal/grpc/codec.go` |
| Env vars não carregando | Config cacheada | `docker compose exec panel php artisan config:clear` |

---

## Arquivos que NÃO devem ir para o git

- `.env` (qualquer um)
- `docker-compose.override.yml` (contém segredos de produção)
- `panel/vendor/`
- `panel/node_modules/`
- `orchestrator/bin/`
- `*.dump` (backups de banco)
