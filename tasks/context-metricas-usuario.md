# Contexto: Redesenhar Sistema de Métricas Focado em Aplicações

**Data:** 2026-04-05
**Slug:** metricas-usuario

---

## Input do Usuário

O usuário identificou que o sistema de métricas está implementado de forma incorreta:

> "A parte de servidores só estava disponível para administradores. Usuários normais terão acesso somente a aplicações, deployments, domínios, monitoramento, log de atividades.
>
> Com isso a parte de métricas deve refletir a realidade das APLICAÇÕES do usuário e não a realidade do servidor em geral."

### Requisitos do Dashboard Geral
- Quantidade de Aplicações
- Containers
- Deploys com Sucesso
- Deployments Hoje
- Deployments com Falha
- Acesso rápido aos aplicativos
- Tabela de deployments recentes
- Tabela de alertas

### Requisitos da Tela de Monitoramento
- Seletor elegante de aplicativo
- Métricas avançadas: Containers, CPU Média, RAM Média, Requisições HTTP
- Gráfico de Requisições HTTP por hora (linha - códigos 200, 300, 400, 500)
- Gráfico de uso de memória RAM por hora (linha - por container com filtro)
- Gráfico de uso de CPU por hora (linha - por container com filtro)
- Terminal de erros: seletor de container + registros

---

## Observações Iniciais

Antes de chamar o arquiteto, observei:

1. **Widgets atuais mostram dados globais**: `StatsOverview` conta servidores, não filtra por usuário
2. **`RecentDeploymentsWidget`**: Lista todos os deployments, não filtrados
3. **`ActiveAlertsWidget`**: Mostra alertas globais sem vínculo com aplicação
4. **`MonitoringDashboard`**: Focado em servidores (CPU, RAM de servidores)
5. **Model `Alert`**: Não tem `application_id` - não pode ser vinculado a uma app
6. **Model `User`**: Não tem `is_admin` - não há como diferenciar admin de usuário normal

---

## Input para o Arquiteto

```
Você é o arquiteto de software do EasyDeploy. Analise a arquitetura atual do sistema de métricas e monitoramento e projete uma nova arquitetura focada na perspectiva do USUÁRIO (aplicações) ao invés de servidores.

## Contexto do Problema

A plataforma atual tem métricas focadas em servidores, mas:
- Usuários normais NÃO têm acesso a servidores
- Usuários normais só veem: aplicações, deployments, domínios, monitoramento, logs de atividades
- As métricas devem refletir a realidade das APLICAÇÕES do usuário

## Requisitos da Nova Arquitetura

### 1. Dashboard Geral (para usuários)
Widgets de métricas:
- Quantidade de Aplicações (do usuário/team)
- Containers (total rodando das apps do usuário)
- Deploys com Sucesso (histórico)
- Deployments Hoje
- Deployments com Falha

Componentes:
- Acesso rápido aos aplicativos
- Tabela de deployments recentes
- Tabela de alertas

### 2. Tela de Monitoramento por Aplicativo
- Seletor elegante de aplicativo
- Métricas avançadas por app:
  - Quantidade de Containers
  - CPU Média
  - Memória RAM Média
  - Requisições HTTP

Gráficos:
- Requisições HTTP por hora (linha) - códigos 200, 300, 400, 500
- Uso de memória RAM por hora (linha) - cada container, com filtro
- Uso de CPU por hora (linha) - cada container, com filtro

Terminal de Logs:
- Seletor de container
- Mostrar registros/erros do container selecionado

## Tarefas de Análise

1. Examine a estrutura atual de métricas
2. Identifique o que já existe e pode ser reaproveitado
3. Identifique o que precisa ser modificado e criado
4. Projete o fluxo de dados das métricas
5. Defina contratos claros (endpoints, estrutura JSON)
6. Liste os arquivos que precisam ser criados/modificados
```

---

## Output do Arquiteto

### Análise do Estado Atual

**O que já existe e pode ser reaproveitado:**

| Componente | Arquivo | Reaproveitável |
|------------|---------|----------------|
| Model `Application` | `panel/app/Models/Application.php` | Sim - já tem `containers()`, `deployments()`, `resourceUsages()` |
| Model `Container` | `panel/app/Models/Container.php` | Sim - já tem `cpu_usage`, `memory_usage` |
| Model `Deployment` | `panel/app/Models/Deployment.php` | Sim - tem status, timestamps |
| Model `ResourceUsage` | `panel/app/Models/ResourceUsage.php` | Sim - armazena métricas por container/app |
| Model `HttpMetric` | `panel/app/Models/HttpMetric.php` | Sim - métricas HTTP por aplicação |
| View de métricas por app | `view-application-metrics.blade.php` | Sim - já tem gráficos |
| Page ViewApplicationMetrics | `ViewApplicationMetrics.php` | Sim - base para expansão |
| Page ViewApplicationLogs | `ViewApplicationLogs.php` | Sim - já tem filtros por container |
| Traefik Scraper | `traefik_scraper.go` | Sim - coleta HTTP por app slug |
| Metrics Collector | `collector.go` | Sim - coleta métricas de containers |
| Agent gRPC GetContainerStats/Logs | `server.go` | Sim - retorna CPU, memória, logs |

**O que precisa ser modificado:**

| Componente | Modificação |
|------------|-------------|
| Model `Alert` | Adicionar `application_id` FK nullable |
| Widget `StatsOverview` | Filtrar por usuário autenticado |
| Widget `RecentDeploymentsWidget` | Filtrar por apps do usuário |
| Widget `ActiveAlertsWidget` | Filtrar alertas das apps do usuário |
| Page `MonitoringDashboard` | Transformar em tela por app com seletor |
| Resource `ServerResource` | Esconder navegação para não-admin |

**O que precisa ser criado:**

| Componente | Descrição |
|------------|-----------|
| Migration `application_id` em alerts | FK para vincular alertas a apps |
| Migration `is_admin` em users | Diferenciar admin de usuário |
| `UserStatsWidget` | Cards: Apps, Containers, Sucesso, Hoje, Falhas |
| `QuickAccessAppsWidget` | Acesso rápido às aplicações |

### Queries Principais Identificadas

```php
$userId = auth()->id();

// Apps do usuário
Application::where('user_id', $userId)->count();

// Containers rodando
Container::whereHas('application', fn($q) => $q->where('user_id', $userId))
    ->where('status', 'running')
    ->count();

// Deploys com sucesso
Deployment::whereHas('application', fn($q) => $q->where('user_id', $userId))
    ->where('status', 'running')
    ->count();
```

### Decisões de Design

1. **Dashboard unificado com widgets** - Mais simples, segue padrão Filament
2. **Logs via polling (5s)** - WebSocket adiciona complexidade desnecessária
3. **Gráficos agregados por padrão, filtro por container** - Visão geral primeiro
4. **Métricas via Eloquent direto** - Dados já estão no PostgreSQL compartilhado
5. **Role-based access via `is_admin`** - Simples e efetivo

---

## Decisões Tomadas

Baseado na análise do arquiteto, decidimos:

1. **Criar 2 migrations**: `alerts.application_id` e `users.is_admin`
2. **Criar 2 widgets novos**: `UserStatsWidget` e `QuickAccessAppsWidget`
3. **Modificar 3 widgets existentes**: `RecentDeploymentsWidget`, `ActiveAlertsWidget`, `StatsOverview` (deprecar)
4. **Redesenhar `MonitoringDashboard`**: Seletor de app, gráficos, logs
5. **Esconder `ServerResource`** para usuários normais via `shouldRegisterNavigation()`

**Total: 16 tarefas em 6 fases**

---

## Design System — Especificações de UI

### Filosofia
- **Profissional + Futurista** — clean mas inovador, não genérico
- **Animações sutis** — micro-interações em hover, entrada, transições
- **Light mode first** — branco como padrão, com suporte completo a dark mode
- **Glassmorphism sutil** — backdrop-blur em cards e modais

### Paleta de Cores
```css
--brand-primary: #0d8bfa;      /* Azul EasyTI */
--brand-secondary: #06b6d4;    /* Cyan */
--brand-gradient: linear-gradient(135deg, #0d8bfa, #06b6d4);

/* Status */
--success: #10b981;  /* Emerald - deploys ok */
--warning: #f59e0b;  /* Amber - alertas */
--danger: #ef4444;   /* Red - falhas */
```

### Stats Cards (UserStatsWidget)
```html
<div class="group relative overflow-hidden rounded-2xl bg-slate-800/50 p-6
            border border-white/5 hover:border-sky-500/30
            transition-all duration-300">
    <!-- Glow on hover -->
    <div class="absolute inset-0 bg-gradient-to-br from-sky-500/0 to-cyan-500/0
                group-hover:from-sky-500/5 group-hover:to-cyan-500/5
                transition-all duration-500"></div>

    <!-- Ícone com cor -->
    <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Apps</span>
        <div class="p-2 rounded-lg bg-sky-500/10">
            <svg class="w-4 h-4 text-sky-500">...</svg>
        </div>
    </div>

    <!-- Valor grande animado -->
    <div class="text-3xl font-bold text-white" x-data="{ count: 0 }" x-intersect="...">
        <span x-text="count">5</span>
    </div>

    <!-- Descrição -->
    <p class="text-sm text-slate-400 mt-1">aplicações ativas</p>
</div>
```

### Quick Access Apps (QuickAccessAppsWidget)
```html
<div class="grid grid-cols-2 md:grid-cols-3 gap-4">
    <a href="#" class="group bg-slate-800/30 rounded-xl p-4 border border-white/5
                       hover:bg-slate-800/50 hover:border-sky-500/30
                       transition-all duration-300">
        <!-- Status dot -->
        <div class="flex items-center gap-2 mb-2">
            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
            <span class="text-xs text-slate-400">Running</span>
        </div>

        <!-- App name -->
        <h3 class="font-medium text-white group-hover:text-sky-400 transition-colors">
            meu-app
        </h3>

        <!-- Quick stats -->
        <div class="flex gap-3 mt-2 text-xs text-slate-500">
            <span>3 containers</span>
            <span>·</span>
            <span>45% CPU</span>
        </div>
    </a>
</div>
```

### Seletor de App (MonitoringDashboard)
```html
<div x-data="{ open: false, selected: null }" class="relative">
    <button @click="open = !open"
            class="flex items-center gap-3 bg-slate-800 border border-slate-700
                   rounded-xl px-4 py-3 min-w-[250px]
                   hover:border-sky-500/50 transition-all duration-200">
        <!-- App icon + name -->
        <div class="flex-1 text-left">
            <span class="text-white font-medium" x-text="selected?.name || 'Selecione um app'"></span>
        </div>
        <!-- Chevron animated -->
        <svg class="w-5 h-5 text-slate-400 transition-transform duration-200"
             :class="{ 'rotate-180': open }">...</svg>
    </button>

    <!-- Dropdown -->
    <div x-show="open" x-transition
         class="absolute mt-2 w-full bg-slate-800 border border-slate-700
                rounded-xl shadow-xl shadow-black/20 overflow-hidden z-50">
        <template x-for="app in apps">
            <button class="w-full px-4 py-3 text-left hover:bg-slate-700/50
                          transition-colors flex items-center gap-3">
                <div class="w-2 h-2 rounded-full" :class="app.statusColor"></div>
                <span class="text-white" x-text="app.name"></span>
            </button>
        </template>
    </div>
</div>
```

### Gráficos (Chart.js Config)
```javascript
const chartConfig = {
    animation: { duration: 750, easing: 'easeOutQuart' },
    elements: {
        line: { tension: 0.4, borderWidth: 2 },
        point: { radius: 0, hoverRadius: 6, hoverBorderWidth: 2 }
    },
    plugins: {
        legend: {
            position: 'bottom',
            labels: { usePointStyle: true, padding: 20, color: '#94a3b8' }
        },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
            cornerRadius: 8,
            padding: 12,
            titleColor: '#f8fafc',
            bodyColor: '#94a3b8'
        }
    },
    scales: {
        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b' } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b' } }
    }
};

// Cores dos gráficos HTTP
const httpColors = {
    '2xx': { border: '#10b981', bg: 'rgba(16,185,129,0.1)' },
    '3xx': { border: '#3b82f6', bg: 'rgba(59,130,246,0.1)' },
    '4xx': { border: '#f59e0b', bg: 'rgba(245,158,11,0.1)' },
    '5xx': { border: '#ef4444', bg: 'rgba(239,68,68,0.1)' }
};
```

### Terminal de Logs
```html
<div class="bg-slate-900 rounded-2xl border border-slate-700 overflow-hidden">
    <!-- Header macOS style -->
    <div class="bg-slate-800/50 px-4 py-3 border-b border-slate-700 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="flex gap-1.5">
                <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
            </div>
            <span class="text-slate-400 text-sm font-medium">Container Logs</span>
        </div>

        <!-- Container selector -->
        <select class="bg-slate-700 border-0 rounded-lg text-sm text-white px-3 py-1.5
                       focus:ring-2 focus:ring-sky-500/50">
            <option>web-1</option>
            <option>web-2</option>
        </select>
    </div>

    <!-- Logs area -->
    <div class="p-4 font-mono text-sm max-h-[400px] overflow-y-auto
                scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent">
        <!-- Log line with timestamp -->
        <div class="flex gap-3 py-0.5 hover:bg-slate-800/30">
            <span class="text-slate-500 shrink-0">10:23:45</span>
            <span class="text-emerald-400 shrink-0">INFO</span>
            <span class="text-slate-300">Server started on port 3000</span>
        </div>
        <div class="flex gap-3 py-0.5 hover:bg-slate-800/30">
            <span class="text-slate-500 shrink-0">10:23:46</span>
            <span class="text-red-400 shrink-0">ERROR</span>
            <span class="text-slate-300">Connection refused to database</span>
        </div>
    </div>
</div>
```

### Animações Padrão
```css
/* Todas as transições usam duration-300 para cards */
transition-all duration-300

/* Hover em cards: border e glow */
hover:border-sky-500/30 hover:shadow-lg hover:shadow-sky-500/10

/* Entrada de elementos (stagger) */
[x-cloak] { display: none; }
.stagger-1 { animation-delay: 0.1s; }
.stagger-2 { animation-delay: 0.2s; }
.stagger-3 { animation-delay: 0.3s; }

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
    animation: fadeInUp 0.4s ease-out forwards;
}
```
