# Plano: Redesenhar Sistema de Métricas Focado em Aplicações

**Data:** 2026-04-05
**Objetivo:** Transformar o sistema de métricas de uma visão centrada em SERVIDORES para uma visão centrada em APLICAÇÕES do usuário.

---

## 1. Contexto e Problema

### Situação Atual
- Dashboard mostra métricas de **servidores** (CPU, RAM, containers globais)
- `StatsOverview` exibe contadores globais sem filtro por usuário
- `RecentDeploymentsWidget` lista deployments de **todos** os usuários
- `ActiveAlertsWidget` mostra alertas globais (sem vínculo com aplicação)
- `MonitoringDashboard` é focado em infraestrutura (servidores)
- Model `Alert` não possui `application_id` nem `user_id`
- Model `User` não tem campo `is_admin`

### Situação Desejada
- Dashboard mostra métricas das **aplicações do usuário logado**
- Widgets filtrados por `user_id` do `auth()->user()`
- Alertas vinculados a aplicações
- Tela de monitoramento com seletor de aplicativo e métricas avançadas
- Usuários normais não veem recursos de servidor

---

## 2. Arquitetura da Solução

### Grafo de Dependências

```
[Migration: alerts.application_id] ─────────────────┐
         │                                          │
         ▼                                          │
[Model: Alert - add application_id + scopes]        │
         │                                          │
         ├──────────────────────────────────────────┤
         ▼                                          ▼
[Widget: UserStatsWidget]              [Widget: ActiveAlertsWidget - filtrar]
         │                                          │
         ▼                                          │
[Widget: QuickAccessAppsWidget]                     │
         │                                          │
         ▼                                          │
[Widget: RecentDeploymentsWidget - filtrar]         │
         │                                          │
         ├──────────────────────────────────────────┤
         ▼                                          ▼
[AdminPanelProvider - registrar widgets na ordem]
         │
         ▼
[Migration: users.is_admin] ──> [User Model: add is_admin]
         │
         ▼
[ServerResource - esconder para não-admin]
         │
         ▼
[MonitoringDashboard - redesenhar com seletor de app]
         │
         ▼
[Blade View - nova UI com gráficos e logs]
         │
         ▼
[ApplicationResource - adicionar ação "Monitorar"]
```

### Componentes Afetados

| Componente | Tipo | Impacto |
|------------|------|---------|
| `panel/database/migrations/` | Migration | Criar 2 novas |
| `panel/app/Models/Alert.php` | Model | Modificar |
| `panel/app/Models/User.php` | Model | Modificar |
| `panel/app/Filament/Widgets/` | Widgets | Criar 2, modificar 3 |
| `panel/app/Filament/Pages/MonitoringDashboard.php` | Page | Redesenhar |
| `panel/resources/views/filament/pages/monitoring-dashboard.blade.php` | View | Redesenhar |
| `panel/app/Filament/Resources/ServerResource.php` | Resource | Modificar |
| `panel/app/Filament/Resources/ApplicationResource.php` | Resource | Modificar |
| `panel/app/Providers/Filament/AdminPanelProvider.php` | Provider | Modificar |

---

## 3. Fases de Implementação

### FASE 1: Base de Dados e Models
**Objetivo:** Preparar estrutura de dados

| # | Tarefa | Tamanho | Arquivos |
|---|--------|---------|----------|
| T1 | Migration: adicionar `application_id` em alerts | XS | 1 |
| T2 | Migration: adicionar `is_admin` em users | XS | 1 |
| T3 | Model Alert: adicionar relação e scopes | XS | 1 |
| T4 | Model User: adicionar `is_admin` attribute | XS | 1 |

**Checkpoint:** `php artisan migrate` sem erros

---

### FASE 2: Widgets do Dashboard
**Objetivo:** Criar widgets focados no usuário

| # | Tarefa | Tamanho | Arquivos |
|---|--------|---------|----------|
| T5 | Criar `UserStatsWidget` (apps, containers, deploys) | S | 1 |
| T6 | Criar `QuickAccessAppsWidget` (grid de apps) | S | 1 |
| T7 | Modificar `RecentDeploymentsWidget` (filtrar por user) | XS | 1 |
| T8 | Modificar `ActiveAlertsWidget` (filtrar por user/app) | XS | 1 |
| T9 | Deprecar `StatsOverview` (remover do dashboard) | XS | 1 |

**Checkpoint:** Dashboard mostra apenas dados do usuário logado

---

### FASE 3: Configuração do Dashboard
**Objetivo:** Registrar widgets na ordem correta

| # | Tarefa | Tamanho | Arquivos |
|---|--------|---------|----------|
| T10 | Configurar `AdminPanelProvider` com novos widgets | XS | 1 |

**Checkpoint:** Dashboard renderiza com layout correto

---

### FASE 4: Controle de Acesso
**Objetivo:** Esconder recursos de admin

| # | Tarefa | Tamanho | Arquivos |
|---|--------|---------|----------|
| T11 | `ServerResource`: esconder navegação para não-admin | XS | 1 |
| T12 | `MonitoringDashboard`: mover para grupo correto | XS | 1 |

**Checkpoint:** Usuário não-admin não vê "Servidores" na sidebar

---

### FASE 5: Tela de Monitoramento por Aplicativo
**Objetivo:** Redesenhar MonitoringDashboard

| # | Tarefa | Tamanho | Arquivos |
|---|--------|---------|----------|
| T13 | `MonitoringDashboard.php`: seletor de app + props | M | 1 |
| T14 | `monitoring-dashboard.blade.php`: nova UI completa | L | 1 |

**Checkpoint:** Tela de monitoramento funcional com gráficos e logs

---

### FASE 6: Integração Final
**Objetivo:** Conectar tudo

| # | Tarefa | Tamanho | Arquivos |
|---|--------|---------|----------|
| T15 | `ApplicationResource`: adicionar ação "Monitorar" | XS | 1 |
| T16 | Testes manuais e ajustes de UI | S | - |

**Checkpoint:** Fluxo completo funcionando

---

## 4. Detalhamento das Tarefas

### T1: Migration para `application_id` em alerts
**Arquivo:** `panel/database/migrations/2026_04_06_000001_add_application_id_to_alerts_table.php`

```php
Schema::table('alerts', function (Blueprint $table) {
    $table->foreignUuid('application_id')->nullable()->after('id')->constrained()->nullOnDelete();
    $table->index('application_id');
});
```

**Critérios de Aceite:**
- [ ] Migration criada
- [ ] `php artisan migrate` executa sem erros
- [ ] Coluna `application_id` existe na tabela `alerts`

**Verificação:**
```bash
php artisan migrate
php artisan tinker --execute="Schema::hasColumn('alerts', 'application_id')"
```

---

### T2: Migration para `is_admin` em users
**Arquivo:** `panel/database/migrations/2026_04_06_000002_add_is_admin_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_admin')->default(false)->after('email');
});
```

**Critérios de Aceite:**
- [ ] Migration criada
- [ ] Coluna `is_admin` existe na tabela `users`
- [ ] Valor padrão é `false`

---

### T3: Model Alert - adicionar relação e scopes
**Arquivo:** `panel/app/Models/Alert.php`

**Mudanças:**
- Adicionar `application_id` no `$fillable`
- Adicionar relação `application()`
- Adicionar scope `forUser($userId)` - retorna alertas das apps do usuário
- Adicionar scope `forApplication($applicationId)`

**Critérios de Aceite:**
- [ ] `Alert::forUser($id)` retorna alertas das apps do usuário
- [ ] `$alert->application` retorna a aplicação relacionada

---

### T4: Model User - adicionar `is_admin`
**Arquivo:** `panel/app/Models/User.php`

**Mudanças:**
- Adicionar `is_admin` no `$fillable`
- Adicionar cast `'is_admin' => 'boolean'`
- Adicionar método `isAdmin(): bool`

**Critérios de Aceite:**
- [ ] `$user->isAdmin()` retorna boolean
- [ ] Cast `is_admin` como boolean funciona

---

### T5: Criar `UserStatsWidget`
**Arquivo:** `panel/app/Filament/Widgets/UserStatsWidget.php`

**Cards a exibir:**
1. **Aplicações** - Quantidade de apps do usuário
2. **Containers** - Containers rodando das apps do usuário
3. **Deploys com Sucesso** - Total histórico
4. **Deployments Hoje** - Deployments criados hoje
5. **Deployments com Falha** - Total de falhas

**Queries:**
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

// Deployments hoje
Deployment::whereHas('application', fn($q) => $q->where('user_id', $userId))
    ->whereDate('created_at', today())
    ->count();

// Falhas
Deployment::whereHas('application', fn($q) => $q->where('user_id', $userId))
    ->where('status', 'failed')
    ->count();
```

**Critérios de Aceite:**
- [ ] Widget exibe 5 cards
- [ ] Dados filtrados pelo usuário logado
- [ ] Mini gráficos de tendência funcionando

---

### T6: Criar `QuickAccessAppsWidget`
**Arquivo:** `panel/app/Filament/Widgets/QuickAccessAppsWidget.php`

**Funcionalidades:**
- Grid de cards das aplicações do usuário
- Status visual (badge colorido)
- Botão rápido para "Editar" e "Monitorar"
- Limitar a 6 apps mais recentes

**Critérios de Aceite:**
- [ ] Exibe apps do usuário logado
- [ ] Links funcionais para editar e monitorar
- [ ] Visual responsivo (2 cols mobile, 3 cols desktop)

---

### T7: Modificar `RecentDeploymentsWidget`
**Arquivo:** `panel/app/Filament/Widgets/RecentDeploymentsWidget.php`

**Mudança:**
```php
// Antes
Deployment::query()->with(['application'])->latest()->limit(10)

// Depois
Deployment::query()
    ->whereHas('application', fn($q) => $q->where('user_id', auth()->id()))
    ->with(['application'])
    ->latest()
    ->limit(10)
```

**Critérios de Aceite:**
- [ ] Apenas deployments das apps do usuário aparecem
- [ ] Funcionalidade preservada

---

### T8: Modificar `ActiveAlertsWidget`
**Arquivo:** `panel/app/Filament/Widgets/ActiveAlertsWidget.php`

**Mudança:**
```php
// Antes
Alert::query()->firing()

// Depois
Alert::query()
    ->firing()
    ->where(function ($q) {
        $q->whereHas('application', fn($q2) => $q2->where('user_id', auth()->id()))
          ->orWhereNull('application_id'); // Alertas globais para admin
    })
```

**Critérios de Aceite:**
- [ ] Usuário vê apenas alertas das suas apps
- [ ] Alertas sem application_id (globais) aparecem para admin

---

### T9: Deprecar `StatsOverview`
**Arquivo:** `panel/app/Filament/Widgets/StatsOverview.php`

**Mudança:** Adicionar `protected static bool $isDiscovered = false;`

**Critérios de Aceite:**
- [ ] Widget não aparece no dashboard padrão
- [ ] Dashboard usa `UserStatsWidget` no lugar

---

### T10: Configurar `AdminPanelProvider`
**Arquivo:** `panel/app/Providers/Filament/AdminPanelProvider.php`

**Ordem dos widgets (via sort no widget):**
1. `UserStatsWidget` (sort: 1)
2. `QuickAccessAppsWidget` (sort: 2)
3. `RecentDeploymentsWidget` (sort: 3)
4. `ActiveAlertsWidget` (sort: 4)

**Critérios de Aceite:**
- [ ] Widgets aparecem na ordem correta
- [ ] Layout responsivo funciona

---

### T11: Esconder `ServerResource` para não-admin
**Arquivo:** `panel/app/Filament/Resources/ServerResource.php`

**Mudança:**
```php
public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->isAdmin() ?? false;
}

public static function canAccess(): bool
{
    return auth()->user()?->isAdmin() ?? false;
}
```

**Critérios de Aceite:**
- [ ] Usuário normal não vê "Servidores" na sidebar
- [ ] Admin continua tendo acesso

---

### T12: Mover `MonitoringDashboard` para grupo correto
**Arquivo:** `panel/app/Filament/Pages/MonitoringDashboard.php`

**Mudança:**
```php
// De
protected static ?string $navigationGroup = 'Infraestrutura';

// Para
protected static ?string $navigationGroup = 'Aplicações';
```

**Critérios de Aceite:**
- [ ] Monitoramento aparece no grupo "Aplicações"

---

### T13: Redesenhar `MonitoringDashboard.php`
**Arquivo:** `panel/app/Filament/Pages/MonitoringDashboard.php`

**Novas propriedades:**
- `public ?string $selectedAppId = null;`
- `public ?string $selectedContainerId = null;`

**Novos métodos:**
- `mount()` - inicializar com primeira app do usuário
- `setSelectedApp($appId)` - trocar app selecionada
- `setSelectedContainer($containerId)` - trocar container
- `getApplicationsProperty()` - apps do usuário
- `getContainersProperty()` - containers da app selecionada
- `getSelectedAppProperty()` - app atualmente selecionada

**Dados para view:**
- `$applications` - lista de apps do usuário
- `$selectedApp` - app selecionada
- `$containers` - containers da app
- `$runningContainers` - contagem
- `$avgCpu` - média de CPU dos containers
- `$avgMemory` - média de RAM dos containers
- `$totalRequests` - requisições HTTP no período
- `$resourceChartData` - dados para gráfico de CPU/RAM
- `$httpChartData` - dados para gráfico HTTP (2xx, 3xx, 4xx, 5xx)
- `$containerLogs` - logs do container selecionado

**Critérios de Aceite:**
- [ ] Seletor de app funciona
- [ ] Métricas atualizam ao trocar app
- [ ] Seletor de período funciona
- [ ] Gráficos renderizam corretamente

---

### T14: Nova UI `monitoring-dashboard.blade.php`
**Arquivo:** `panel/resources/views/filament/pages/monitoring-dashboard.blade.php`

**Layout:**
```
+--------------------------------------------------+
|  MONITORAMENTO                                   |
+--------------------------------------------------+
|  [Seletor de App ▼]              [1h][6h][24h][7d]|
+--------------------------------------------------+
|  Cards: Containers | CPU% | RAM% | Requests     |
+--------------------------------------------------+
|  +---------------------+ +---------------------+ |
|  | Gráfico HTTP        | | Gráfico Recursos    | |
|  | (linhas: 2xx,3xx,   | | (linhas: CPU, RAM   | |
|  |  4xx,5xx por hora)  | |  por container)     | |
|  +---------------------+ +---------------------+ |
+--------------------------------------------------+
|  LOGS DO CONTAINER                               |
|  [Seletor Container ▼]  [Auto-refresh: 5s]      |
|  +----------------------------------------------+|
|  | terminal-style logs here                     ||
|  | ...                                          ||
|  +----------------------------------------------+|
+--------------------------------------------------+
```

**Componentes:**
1. **Header** - Título + seletor de app (Select Alpine.js)
2. **Period selector** - Botões 1h/6h/24h/7d
3. **Stats cards** - 4 cards (Containers, CPU, RAM, Requests)
4. **Gráfico HTTP** - Line chart com 4 séries (2xx, 3xx, 4xx, 5xx)
5. **Gráfico Recursos** - Line chart CPU/RAM com filtro por container
6. **Terminal de logs** - Seletor de container + área de logs estilo terminal

**Critérios de Aceite:**
- [ ] Seletor de app elegante com Alpine.js
- [ ] Gráfico HTTP com 4 séries coloridas
- [ ] Gráfico de recursos com filtro por container
- [ ] Terminal de logs com polling automático (5s)
- [ ] Scroll automático para novas linhas

---

### T15: Ação "Monitorar" no `ApplicationResource`
**Arquivo:** `panel/app/Filament/Resources/ApplicationResource.php`

**Adicionar na tabela `actions`:**
```php
Tables\Actions\Action::make('monitor')
    ->label('Monitorar')
    ->icon('heroicon-o-chart-bar')
    ->color('info')
    ->url(fn (Application $record) => route('filament.admin.pages.monitoring-dashboard', ['app' => $record->id])),
```

**Critérios de Aceite:**
- [ ] Botão "Monitorar" aparece na tabela de apps
- [ ] Clique leva para MonitoringDashboard com app pré-selecionada

---

## 5. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Logs pesados com muitos usuários | Média | Alto | Polling 5s + limite 500 linhas |
| Queries lentas em métricas | Média | Médio | Índices já existem; usar `LIMIT` |
| Usuário sem apps vê dashboard vazio | Alta | Baixo | Mostrar CTA "Crie sua primeira app" |
| Alertas antigos sem `application_id` | Alta | Baixo | FK nullable; alertas globais continuam |

---

## 6. Verificação Final

### Checklist de Aceite do Projeto

**Dashboard do Usuário:**
- [ ] Mostra apenas dados das apps do usuário logado
- [ ] Cards: Apps, Containers, Sucesso, Hoje, Falhas
- [ ] Acesso rápido às aplicações
- [ ] Deployments recentes filtrados
- [ ] Alertas filtrados

**Tela de Monitoramento:**
- [ ] Seletor elegante de aplicativo
- [ ] Métricas: Containers, CPU, RAM, Requests
- [ ] Gráfico HTTP por hora (2xx, 3xx, 4xx, 5xx)
- [ ] Gráfico recursos por container
- [ ] Terminal de logs funcional

**Controle de Acesso:**
- [ ] Usuário normal não vê Servidores
- [ ] Admin vê tudo

---

## 7. Comandos de Verificação

```bash
# Fase 1
php artisan migrate
php artisan tinker --execute="Schema::hasColumn('alerts', 'application_id')"
php artisan tinker --execute="Schema::hasColumn('users', 'is_admin')"

# Fase 2-4
php artisan serve
# Acessar /admin e verificar dashboard

# Fase 5
# Testar seletor de app no /admin/monitoring-dashboard

# Final
docker compose restart panel queue-worker
```

---

## 8. Estimativas

| Fase | Tarefas | Arquivos |
|------|---------|----------|
| FASE 1: Base de Dados e Models | T1-T4 | 4 |
| FASE 2: Widgets do Dashboard | T5-T9 | 5 |
| FASE 3: Configuração Dashboard | T10 | 1 |
| FASE 4: Controle de Acesso | T11-T12 | 2 |
| FASE 5: Monitoramento por App | T13-T14 | 2 |
| FASE 6: Integração Final | T15-T16 | 1 |
| **TOTAL** | **16 tarefas** | **15 arquivos** |

---

**Autor**: Claude Agent
**Aprovação Pendente**: @vinicius
