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
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->prefix('https://')
                            ->suffix('.easyti.cloud')
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(ApplicationType::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $type = ApplicationType::from($state);
                                    $set('port', $type->getDefaultPort());
                                    $set('build_command', $type->getDefaultBuildCommand());
                                    $set('start_command', $type->getDefaultStartCommand());
                                }
                            }),

                        Forms\Components\Select::make('status')
                            ->options(ApplicationStatus::class)
                            ->default('stopped')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Repositório Git')
                    ->schema([
                        Forms\Components\TextInput::make('git_repository')
                            ->label('URL do repositório')
                            ->url()
                            ->placeholder('https://github.com/user/repo'),

                        Forms\Components\TextInput::make('git_branch')
                            ->label('Branch')
                            ->default('main'),

                        Forms\Components\TextInput::make('git_token')
                            ->label('Token de acesso')
                            ->password()
                            ->revealable()
                            ->helperText('Para repositórios privados'),

                        Forms\Components\TextInput::make('root_directory')
                            ->label('Diretório raiz')
                            ->default('/')
                            ->helperText('Caminho relativo ao root do repositório'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Build & Deploy')
                    ->schema([
                        Forms\Components\TextInput::make('build_command')
                            ->label('Comando de build')
                            ->placeholder('npm run build'),

                        Forms\Components\TextInput::make('start_command')
                            ->label('Comando de inicialização')
                            ->placeholder('npm start'),

                        Forms\Components\TextInput::make('port')
                            ->label('Porta')
                            ->numeric()
                            ->default(3000)
                            ->minValue(1)
                            ->maxValue(65535),

                        Forms\Components\Toggle::make('auto_deploy')
                            ->label('Deploy automático')
                            ->helperText('Fazer deploy automaticamente ao receber push')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Recursos')
                    ->schema([
                        Forms\Components\TextInput::make('replicas')
                            ->label('Réplicas')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10),

                        Forms\Components\TextInput::make('cpu_limit')
                            ->label('Limite de CPU')
                            ->numeric()
                            ->default(1000)
                            ->suffix('millicores')
                            ->helperText('1000 = 1 CPU'),

                        Forms\Components\TextInput::make('memory_limit')
                            ->label('Limite de memória')
                            ->numeric()
                            ->default(512)
                            ->suffix('MB'),

                        Forms\Components\Toggle::make('auto_scale')
                            ->label('Auto-scaling')
                            ->helperText('Escalar automaticamente baseado no uso'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Auto-scaling')
                    ->schema([
                        Forms\Components\TextInput::make('min_replicas')
                            ->label('Mínimo de réplicas')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),

                        Forms\Components\TextInput::make('max_replicas')
                            ->label('Máximo de réplicas')
                            ->numeric()
                            ->default(5)
                            ->minValue(1),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('auto_scale')),

                Forms\Components\Section::make('Health Check')
                    ->schema([
                        Forms\Components\TextInput::make('health_check.path')
                            ->label('Caminho')
                            ->default('/health'),

                        Forms\Components\TextInput::make('health_check.interval')
                            ->label('Intervalo')
                            ->numeric()
                            ->default(30)
                            ->suffix('segundos'),
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('containers_count')
                    ->counts('containers')
                    ->label('Containers'),

                Tables\Columns\TextColumn::make('primaryDomain.domain')
                    ->label('Domínio')
                    ->url(fn ($record) => $record->primaryDomain?->url)
                    ->openUrlInNewTab()
                    ->placeholder($record->default_domain ?? '-'),

                Tables\Columns\TextColumn::make('latestDeployment.status')
                    ->label('Último deploy')
                    ->badge(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ApplicationType::class),

                Tables\Filters\SelectFilter::make('status')
                    ->options(ApplicationStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('deploy')
                    ->label('Deploy')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar deploy')
                    ->modalDescription('Deseja iniciar um novo deploy desta aplicação?')
                    ->action(fn (Application $record) => static::triggerDeploy($record)),

                Tables\Actions\Action::make('logs')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Application $record) => static::getUrl('logs', ['record' => $record])),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
        // TODO: Implement deployment trigger via OrchestratorClient
    }
}
