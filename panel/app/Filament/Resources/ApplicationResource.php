<?php

namespace App\Filament\Resources;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Filament\Resources\ApplicationResource\Pages;
use App\Filament\Resources\ApplicationResource\RelationManagers;
use App\Models\Application;
use Filament\Forms;
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
                Forms\Components\Section::make('Informações básicas')
                    ->description('Configure o nome e tipo da sua aplicação')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText('Nome descritivo da sua aplicação')
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->prefix('https://')
                            ->suffix('.easyti.cloud')
                            ->maxLength(255)
                            ->helperText('URL onde sua aplicação será acessada'),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(ApplicationType::class)
                            ->required()
                            ->live()
                            ->helperText('Escolha a linguagem/runtime da sua aplicação')
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $type = ApplicationType::from($state);
                                    $set('port', $type->getDefaultPort());
                                    $set('build_command', $type->getDefaultBuildCommand());
                                    $set('start_command', $type->getDefaultStartCommand());
                                }
                            }),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ApplicationStatus::class)
                            ->default('stopped')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Gerenciado automaticamente pelo sistema'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Repositório Git')
                    ->description('Conecte seu repositório para deploy automático')
                    ->icon('heroicon-o-code-bracket')
                    ->schema([
                        Forms\Components\TextInput::make('git_repository')
                            ->label('URL do repositório')
                            ->url()
                            ->placeholder('https://github.com/usuario/repositorio')
                            ->helperText('URL HTTPS do seu repositório Git (GitHub, GitLab, etc.)'),

                        Forms\Components\TextInput::make('git_branch')
                            ->label('Branch')
                            ->default('main')
                            ->helperText('Branch que será monitorada para deploys'),

                        Forms\Components\TextInput::make('git_token')
                            ->label('Token de acesso')
                            ->password()
                            ->revealable()
                            ->helperText('Personal Access Token para repositórios privados (opcional)'),

                        Forms\Components\TextInput::make('root_directory')
                            ->label('Diretório raiz')
                            ->default('/')
                            ->placeholder('/')
                            ->helperText('Use "/" para raiz ou "/backend" para subdiretório'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Build & Deploy')
                    ->description('Configure como sua aplicação será compilada e iniciada')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\TextInput::make('build_command')
                            ->label('Comando de build')
                            ->placeholder('npm run build')
                            ->helperText('Comando executado antes do deploy (ex: npm run build, go build)'),

                        Forms\Components\TextInput::make('start_command')
                            ->label('Comando de inicialização')
                            ->placeholder('npm start')
                            ->helperText('Comando que inicia sua aplicação (ex: npm start, python app.py)'),

                        Forms\Components\TextInput::make('port')
                            ->label('Porta')
                            ->numeric()
                            ->default(3000)
                            ->minValue(1)
                            ->maxValue(65535)
                            ->helperText('Porta onde sua aplicação escuta (geralmente 3000, 8000 ou 8080)'),

                        Forms\Components\Toggle::make('auto_deploy')
                            ->label('Deploy automático')
                            ->helperText('Fazer deploy automaticamente ao receber push no Git')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Recursos')
                    ->description('Defina limites de CPU e memória')
                    ->icon('heroicon-o-server-stack')
                    ->schema([
                        Forms\Components\TextInput::make('replicas')
                            ->label('Réplicas')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText('Número de containers em execução (para balanceamento de carga)'),

                        Forms\Components\TextInput::make('cpu_limit')
                            ->label('Limite de CPU')
                            ->numeric()
                            ->default(1000)
                            ->suffix('millicores')
                            ->helperText('1000 millicores = 1 CPU core completo'),

                        Forms\Components\TextInput::make('memory_limit')
                            ->label('Limite de memória')
                            ->numeric()
                            ->default(512)
                            ->suffix('MB')
                            ->helperText('Memória RAM máxima permitida'),

                        Forms\Components\Toggle::make('auto_scale')
                            ->label('Auto-scaling')
                            ->helperText('Escalar automaticamente baseado na utilização de recursos'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Auto-scaling')
                    ->description('Configuração de escala automática')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->schema([
                        Forms\Components\TextInput::make('min_replicas')
                            ->label('Mínimo de réplicas')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Número mínimo de containers sempre ativos'),

                        Forms\Components\TextInput::make('max_replicas')
                            ->label('Máximo de réplicas')
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->helperText('Número máximo de containers permitido'),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('auto_scale')),

                Forms\Components\Section::make('Health Check')
                    ->description('Monitoramento de saúde da aplicação')
                    ->icon('heroicon-o-heart')
                    ->schema([
                        Forms\Components\TextInput::make('health_check.path')
                            ->label('Caminho')
                            ->default('/health')
                            ->placeholder('/health')
                            ->helperText('Endpoint HTTP para verificar se a aplicação está saudável'),

                        Forms\Components\TextInput::make('health_check.interval')
                            ->label('Intervalo')
                            ->numeric()
                            ->default(30)
                            ->suffix('segundos')
                            ->helperText('Frequência das verificações de saúde'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Application $record) => $record->slug),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
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
                    ->copyable()
                    ->copyMessage('Domínio copiado!')
                    ->limit(40),

                Tables\Columns\TextColumn::make('latestDeployment.status')
                    ->label('Último deploy')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
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

                Tables\Actions\Action::make('logs')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Application $record) => static::getUrl('logs', ['record' => $record])),

                Tables\Actions\EditAction::make()
                    ->color('warning'),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll('10s')
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
