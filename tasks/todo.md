# TODO: Redesenhar Sistema de Métricas Focado em Aplicações

**Status**: 🟡 Aguardando Aprovação | **Data**: 2026-04-05

---

## FASE 1: Base de Dados e Models 🗄️

- [ ] **T1** - Migration: adicionar `application_id` em alerts (XS)
  - Arquivo: `panel/database/migrations/2026_04_06_000001_add_application_id_to_alerts_table.php`
  - FK nullable, index
  - Rodar: `php artisan migrate`
  - Verificar: `Schema::hasColumn('alerts', 'application_id')`

- [ ] **T2** - Migration: adicionar `is_admin` em users (XS)
  - Arquivo: `panel/database/migrations/2026_04_06_000002_add_is_admin_to_users_table.php`
  - Boolean default false
  - Verificar: `Schema::hasColumn('users', 'is_admin')`

- [ ] **T3** - Model Alert: adicionar relação e scopes (XS)
  - Arquivo: `panel/app/Models/Alert.php`
  - Adicionar: `application_id` em fillable
  - Adicionar: `application()` relationship
  - Adicionar: `scopeForUser($userId)`
  - Adicionar: `scopeForApplication($appId)`

- [ ] **T4** - Model User: adicionar `is_admin` (XS)
  - Arquivo: `panel/app/Models/User.php`
  - Adicionar: `is_admin` em fillable
  - Adicionar: `'is_admin' => 'boolean'` em casts
  - Adicionar: método `isAdmin(): bool`

- [ ] **CHECKPOINT 1**: Migrations aplicadas sem erro ✅

---

## FASE 2: Widgets do Dashboard 🎛️

- [ ] **T5** - Criar `UserStatsWidget` (S)
  - Arquivo: `panel/app/Filament/Widgets/UserStatsWidget.php`
  - Cards: Apps, Containers, Sucesso, Hoje, Falhas
  - Filtrar por `auth()->id()`
  - Mini charts de tendência

- [ ] **T6** - Criar `QuickAccessAppsWidget` (S)
  - Arquivo: `panel/app/Filament/Widgets/QuickAccessAppsWidget.php`
  - Grid de apps do usuário
  - Status visual, links Editar/Monitorar
  - Limite 6 apps

- [ ] **T7** - Modificar `RecentDeploymentsWidget` (XS)
  - Arquivo: `panel/app/Filament/Widgets/RecentDeploymentsWidget.php`
  - Filtrar: `whereHas('application', fn($q) => $q->where('user_id', auth()->id()))`

- [ ] **T8** - Modificar `ActiveAlertsWidget` (XS)
  - Arquivo: `panel/app/Filament/Widgets/ActiveAlertsWidget.php`
  - Filtrar alertas das apps do usuário
  - Preservar alertas globais (application_id = null) para admin

- [ ] **T9** - Deprecar `StatsOverview` (XS)
  - Arquivo: `panel/app/Filament/Widgets/StatsOverview.php`
  - Adicionar: `protected static bool $isDiscovered = false;`

- [ ] **CHECKPOINT 2**: Dashboard mostra apenas dados do usuário ✅

---

## FASE 3: Configuração do Dashboard ⚙️

- [ ] **T10** - Configurar `AdminPanelProvider` (XS)
  - Arquivo: `panel/app/Providers/Filament/AdminPanelProvider.php`
  - Ordem de widgets via `protected static ?int $sort`
  - Verificar layout responsivo

- [ ] **CHECKPOINT 3**: Dashboard renderiza corretamente ✅

---

## FASE 4: Controle de Acesso 🔒

- [ ] **T11** - Esconder `ServerResource` para não-admin (XS)
  - Arquivo: `panel/app/Filament/Resources/ServerResource.php`
  - Adicionar: `shouldRegisterNavigation()` → false para não-admin
  - Adicionar: `canAccess()` → false para não-admin

- [ ] **T12** - Mover `MonitoringDashboard` para grupo correto (XS)
  - Arquivo: `panel/app/Filament/Pages/MonitoringDashboard.php`
  - Mudar: `$navigationGroup = 'Aplicações'`

- [ ] **CHECKPOINT 4**: Usuário não-admin não vê Servidores ✅

---

## FASE 5: Tela de Monitoramento por Aplicativo 📊

- [ ] **T13** - Redesenhar `MonitoringDashboard.php` (M)
  - Arquivo: `panel/app/Filament/Pages/MonitoringDashboard.php`
  - Props: `$selectedAppId`, `$selectedContainerId`, `$period`
  - Métodos: `setSelectedApp()`, `setSelectedContainer()`, `setPeriod()`
  - Computed: `getApplicationsProperty()`, `getContainersProperty()`
  - Dados: métricas, gráficos, logs

- [ ] **T14** - Nova UI `monitoring-dashboard.blade.php` (L)
  - Arquivo: `panel/resources/views/filament/pages/monitoring-dashboard.blade.php`
  - Seletor de app (Alpine.js)
  - Seletor de período (1h, 6h, 24h, 7d)
  - 4 cards de métricas
  - Gráfico HTTP (2xx, 3xx, 4xx, 5xx)
  - Gráfico recursos (CPU, RAM por container)
  - Terminal de logs com seletor de container

- [ ] **CHECKPOINT 5**: Monitoramento por app funcional ✅

---

## FASE 6: Integração Final 🔗

- [ ] **T15** - Ação "Monitorar" no `ApplicationResource` (XS)
  - Arquivo: `panel/app/Filament/Resources/ApplicationResource.php`
  - Adicionar action na tabela
  - Link para MonitoringDashboard com `?app={id}`

- [ ] **T16** - Testes manuais e ajustes de UI (S)
  - Testar fluxo completo
  - Verificar responsividade
  - Ajustar cores/espaçamentos

- [ ] **CHECKPOINT FINAL**: Fluxo completo funcionando ✅

---

## Verificação Final 🚀

**Dashboard do Usuário:**
- [ ] Cards: Apps, Containers, Sucesso, Hoje, Falhas
- [ ] Acesso rápido aos apps
- [ ] Deployments recentes filtrados
- [ ] Alertas filtrados

**Monitoramento por App:**
- [ ] Seletor de app elegante
- [ ] Métricas: Containers, CPU, RAM, HTTP
- [ ] Gráfico HTTP (2xx, 3xx, 4xx, 5xx)
- [ ] Gráfico recursos por container
- [ ] Terminal de logs

**Acesso:**
- [ ] Usuário não vê Servidores
- [ ] Admin vê tudo

---

## Comandos Úteis

```bash
# Migrations
php artisan migrate
php artisan migrate:rollback

# Verificar schema
php artisan tinker --execute="Schema::hasColumn('alerts', 'application_id')"
php artisan tinker --execute="Schema::hasColumn('users', 'is_admin')"

# Restart services
docker compose restart panel queue-worker

# Cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

**Arquivos a Criar:**
1. `panel/database/migrations/2026_04_06_000001_add_application_id_to_alerts_table.php`
2. `panel/database/migrations/2026_04_06_000002_add_is_admin_to_users_table.php`
3. `panel/app/Filament/Widgets/UserStatsWidget.php`
4. `panel/app/Filament/Widgets/QuickAccessAppsWidget.php`

**Arquivos a Modificar:**
1. `panel/app/Models/Alert.php`
2. `panel/app/Models/User.php`
3. `panel/app/Filament/Widgets/RecentDeploymentsWidget.php`
4. `panel/app/Filament/Widgets/ActiveAlertsWidget.php`
5. `panel/app/Filament/Widgets/StatsOverview.php`
6. `panel/app/Providers/Filament/AdminPanelProvider.php`
7. `panel/app/Filament/Resources/ServerResource.php`
8. `panel/app/Filament/Pages/MonitoringDashboard.php`
9. `panel/resources/views/filament/pages/monitoring-dashboard.blade.php`
10. `panel/app/Filament/Resources/ApplicationResource.php`

---

**Total:** 16 tarefas | 14 arquivos
