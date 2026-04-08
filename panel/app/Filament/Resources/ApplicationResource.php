<?php

namespace App\Filament\Resources;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Filament\Resources\ApplicationResource\Pages;
use App\Filament\Resources\ApplicationResource\RelationManagers;
use App\Models\Application;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Aplicações';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Aplicação';

    protected static ?string $pluralModelLabel = 'Aplicações';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                static::applicationWizard(auth()->user()),
            ]);
    }

    public static function applicationWizard(?User $owner = null): Wizard
    {
        return Wizard::make([
            Wizard\Step::make('Informações Básicas')
                ->description('Nome, runtime e URL da aplicação')
                ->icon('heroicon-o-cube')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Aplicação')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->prefixIcon('heroicon-o-tag')
                        ->placeholder('Ex: API Produção, Frontend Web, Worker Queue')
                        ->helperText('Nome exibido no dashboard e nas notificações de deploy')
                        ->hint('Use nomes descritivos como "API Users v2" ou "Frontend Marketing"')
                        ->hintIcon('heroicon-m-light-bulb')
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),

                    Forms\Components\TextInput::make('slug')
                        ->label('Subdomínio')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->prefix('https://')
                        ->suffix('.apps.easyti.cloud')
                        ->maxLength(255)
                        ->helperText('Gerado automaticamente — pode personalizar com letras, números e hífens')
                        ->hint('Será seu domínio público após o deploy')
                        ->hintIcon('heroicon-o-globe-alt'),

                    Forms\Components\Select::make('type')
                        ->label('Runtime / Linguagem')
                        ->options(ApplicationType::class)
                        ->required()
                        ->live()
                        ->native(false)
                        ->prefixIcon('heroicon-o-cpu-chip')
                        ->helperText('Porta e comandos padrão são preenchidos automaticamente')
                        ->hint('Node.js · Python · PHP · Go · Ruby · Java')
                        ->hintIcon('heroicon-m-sparkles')
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state) {
                                $type = ApplicationType::from($state);
                                $set('port', $type->getDefaultPort());
                                $set('build_command', $type->getDefaultBuildCommand());
                                $set('start_command', $type->getDefaultStartCommand());
                            }
                        }),

                    Forms\Components\Select::make('status')
                        ->label('Status atual')
                        ->options(ApplicationStatus::class)
                        ->default('stopped')
                        ->disabled()
                        ->dehydrated(false)
                        ->prefixIcon('heroicon-o-signal')
                        ->helperText('Gerenciado automaticamente pelo sistema após cada deploy')
                        ->visible(fn () => filled(request()->route('record'))),
                ])
                ->columns(2),

            Wizard\Step::make('Repositório Git')
                ->description('Conecte seu repositório para deploys automáticos')
                ->icon('heroicon-o-code-bracket')
                ->schema([
                    Forms\Components\TextInput::make('git_repository')
                        ->label('URL do repositório')
                        ->url()
                        ->prefixIcon('heroicon-o-code-bracket-square')
                        ->placeholder('https://github.com/sua-org/seu-projeto')
                        ->helperText('GitHub, GitLab, Bitbucket ou qualquer servidor Git com HTTPS')
                        ->hint('Use HTTPS — autenticação via token abaixo')
                        ->hintIcon('heroicon-o-lock-closed')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('git_branch')
                        ->label('Branch de deploy')
                        ->default('main')
                        ->prefixIcon('heroicon-o-bookmark')
                        ->placeholder('main')
                        ->helperText('Pushes nesta branch disparam deploys automáticos'),

                    Forms\Components\TextInput::make('root_directory')
                        ->label('Diretório raiz')
                        ->default('/')
                        ->prefixIcon('heroicon-o-folder')
                        ->placeholder('/')
                        ->helperText('Use "/" para raiz ou "/api" para monorepos'),

                    Forms\Components\TextInput::make('git_token')
                        ->label('Token de acesso (PAT)')
                        ->password()
                        ->revealable()
                        ->prefixIcon('heroicon-o-key')
                        ->placeholder('ghp_xxxxxxxxxxxxxxxxxxxx')
                        ->helperText('Personal Access Token — obrigatório para repositórios privados')
                        ->hint('Armazenado com criptografia AES-256')
                        ->hintIcon('heroicon-o-shield-check')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Wizard\Step::make('Build & Deploy')
                ->description('Comandos de compilação e inicialização')
                ->icon('heroicon-o-rocket-launch')
                ->schema([
                    Forms\Components\TextInput::make('build_command')
                        ->label('Comando de build')
                        ->prefixIcon('heroicon-o-wrench-screwdriver')
                        ->placeholder('npm run build')
                        ->helperText('Executado antes de iniciar — compilações, bundling, migrações')
                        ->hint('Deixe vazio se não houver etapa de build')
                        ->hintIcon('heroicon-o-information-circle'),

                    Forms\Components\TextInput::make('start_command')
                        ->label('Comando de inicialização')
                        ->prefixIcon('heroicon-o-play')
                        ->placeholder('npm start')
                        ->helperText('Mantém a aplicação em execução contínua no container'),

                    Forms\Components\TextInput::make('port')
                        ->label('Porta da aplicação')
                        ->numeric()
                        ->default(3000)
                        ->minValue(1)
                        ->maxValue(65535)
                        ->prefixIcon('heroicon-o-signal')
                        ->suffix('TCP')
                        ->helperText('Porta interna onde a app escuta — exposta via Traefik')
                        ->hint('Node: 3000 · Python/PHP: 8000 · Go/Java: 8080')
                        ->hintIcon('heroicon-m-light-bulb'),

                    Forms\Components\Toggle::make('auto_deploy')
                        ->label('Deploy automático via git push')
                        ->helperText('Acionar deploy ao detectar novo push no branch configurado')
                        ->default(true)
                        ->onIcon('heroicon-m-bolt')
                        ->offIcon('heroicon-m-bolt-slash')
                        ->onColor('success'),
                ])
                ->columns(2),

            Wizard\Step::make('Recursos')
                ->description('CPU, memória, réplicas, auto-scaling e health check')
                ->icon('heroicon-o-server-stack')
                ->schema(array_merge(
                    static::resourcesSchema($owner),
                    [
                        Forms\Components\Toggle::make('auto_scale')
                            ->label('Auto-scaling habilitado')
                            ->helperText('Aumentar ou reduzir réplicas automaticamente com base na utilização de CPU e memória')
                            ->onIcon('heroicon-m-arrow-trending-up')
                            ->offIcon('heroicon-m-minus')
                            ->onColor('warning'),

                        Forms\Components\TextInput::make('min_replicas')
                            ->label('Mínimo de réplicas')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->prefixIcon('heroicon-o-arrow-down-circle')
                            ->suffix('containers')
                            ->helperText('Containers sempre ativos — garante disponibilidade mesmo com carga zero')
                            ->visible(fn (Forms\Get $get) => (bool) $get('auto_scale')),

                        Forms\Components\TextInput::make('max_replicas')
                            ->label('Máximo de réplicas')
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->prefixIcon('heroicon-o-arrow-up-circle')
                            ->suffix('containers')
                            ->helperText('Teto de escalonamento — protege contra consumo excessivo de recursos do cluster')
                            ->visible(fn (Forms\Get $get) => (bool) $get('auto_scale')),

                        Forms\Components\TextInput::make('health_check.path')
                            ->label('Endpoint de health check')
                            ->default('/health')
                            ->prefixIcon('heroicon-o-heart')
                            ->placeholder('/health')
                            ->helperText('Deve retornar HTTP 200 quando a aplicação estiver operacional')
                            ->hint('Crie um endpoint simples que retorne HTTP 200')
                            ->hintIcon('heroicon-m-light-bulb'),

                        Forms\Components\TextInput::make('health_check.interval')
                            ->label('Intervalo de verificação')
                            ->numeric()
                            ->default(30)
                            ->prefixIcon('heroicon-o-clock')
                            ->suffix('segundos')
                            ->helperText('Frequência com que o sistema verifica se a aplicação responde corretamente'),
                    ]
                ))
                ->columns(2),
        ])
            ->skippable()
            ->persistStepInQueryString('step')
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'ed-app-wizard',
            ]);
    }

    public static function resourcesSchema(?User $owner = null): array
    {
        $limits = $owner
            ? $owner->getPlanLimits()
            : config('easydeploy.plans.enterprise');

        return [
            Forms\Components\TextInput::make('replicas')
                ->label('Réplicas iniciais')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue($limits['max_containers'])
                ->prefixIcon('heroicon-o-server-stack')
                ->suffix('containers')
                ->helperText('Instâncias paralelas para balanceamento de carga. Limite do plano: ' . $limits['max_containers'] . ' containers')
                ->hint('Cada réplica é um container independente no servidor worker')
                ->hintIcon('heroicon-o-information-circle')
                ->rules([
                    function () use ($owner) {
                        return function (string $attribute, mixed $value, \Closure $fail) use ($owner) {
                            if (! $owner) {
                                return;
                            }
                            $record = request()->route('record');
                            $excludeId = $record instanceof Application ? $record->getKey() : null;
                            if (! $owner->canScaleTo((int) $value, $excludeId)) {
                                $limits = $owner->getPlanLimits();
                                $fail("Limite de containers do plano atingido ({$limits['max_containers']} no total).");
                            }
                        };
                    },
                ]),

            Forms\Components\TextInput::make('cpu_limit')
                ->label('Limite de CPU')
                ->numeric()
                ->default(1000)
                ->minValue(100)
                ->maxValue($limits['cpu_limit'])
                ->prefixIcon('heroicon-o-cpu-chip')
                ->suffix('m')
                ->helperText('Máximo de CPU por container. Plano atual: até ' . $limits['cpu_limit'] . 'm')
                ->hint('250m = ¼ vCPU · 500m = ½ vCPU · 1000m = 1 vCPU completo')
                ->hintIcon('heroicon-m-light-bulb'),

            Forms\Components\TextInput::make('memory_limit')
                ->label('Limite de memória RAM')
                ->numeric()
                ->default(512)
                ->minValue(64)
                ->maxValue($limits['memory_limit'])
                ->prefixIcon('heroicon-o-circle-stack')
                ->suffix('MB')
                ->helperText('Memória máxima por container. Plano atual: até ' . $limits['memory_limit'] . ' MB')
                ->hint('Node/Python: 256–512 MB · Go/Rust: 128–256 MB · Java: 512+ MB')
                ->hintIcon('heroicon-m-light-bulb'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Aplicação')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(fn (Application $record) => $record->type?->getIcon())
                    ->iconColor(fn (Application $record) => $record->type?->getColor())
                    ->description(fn (Application $record) => $record->slug . '.' . config('easydeploy.domain.default_suffix')),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Application $record): string => $record->runtime_label)
                    ->color(fn (Application $record): string => match ($record->runtime_state) {
                        'up' => 'success',
                        'down' => 'danger',
                        'deploying' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('containers_count')
                    ->counts('containers')
                    ->label('Containers')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('primaryDomain.domain')
                    ->label('Domínio')
                    ->url(fn ($record) => $record->primaryDomain?->url)
                    ->openUrlInNewTab()
                    ->placeholder(fn ($record) => $record->default_domain ?? '-')
                    ->icon('heroicon-m-globe-alt')
                    ->iconColor('primary')
                    ->copyable()
                    ->copyMessage('Domínio copiado!')
                    ->limit(35),

                Tables\Columns\TextColumn::make('latestDeployment.status')
                    ->label('Último Deploy')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->updated_at?->format('d/m/Y H:i:s'))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(ApplicationType::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ApplicationStatus::class)
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\Action::make('deploy')
                    ->label('Deploy')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar deploy')
                    ->modalDescription('Deseja iniciar um novo deploy desta aplicação?')
                    ->modalIcon('heroicon-o-rocket-launch')
                    ->action(fn (Application $record) => static::triggerDeploy($record)),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('logs')
                        ->label('Logs')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->url(fn (Application $record) => static::getUrl('logs', ['record' => $record])),

                    Tables\Actions\Action::make('monitor')
                        ->label('Monitorar')
                        ->icon('heroicon-o-chart-bar')
                        ->color('primary')
                        ->url(fn (Application $record) => route('filament.admin.pages.monitoring-dashboard', ['app' => $record->id])),

                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Excluir'),
                ])
                    ->label('Ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Mais ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DeploymentsRelationManager::class,
            RelationManagers\ContainersRelationManager::class,
            RelationManagers\DomainsRelationManager::class,
            RelationManagers\EnvironmentVariablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplications::route('/'),
            'create' => Pages\CreateApplication::route('/create'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
            'logs' => Pages\ViewApplicationLogs::route('/{record}/logs'),
            'metrics' => Pages\ViewApplicationMetrics::route('/{record}/metrics'),
        ];
    }

    protected static function triggerDeploy(Application $record): void
    {
        // Dispatch deployment job to queue
        \App\Jobs\ProcessDeploymentJob::dispatch($record);

        \Filament\Notifications\Notification::make()
            ->title('Deploy iniciado')
            ->body('Deploy foi adicionado à fila de processamento.')
            ->success()
            ->send();
    }
}
