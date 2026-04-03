<?php

namespace App\Filament\Resources;

use App\Enums\DeploymentStatus;
use App\Filament\Resources\DeploymentResource\Pages;
use App\Models\Deployment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeploymentResource extends Resource
{
    protected static ?string $model = Deployment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationGroup = 'Aplicações';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Deployment';

    protected static ?string $pluralModelLabel = 'Deployments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do deployment')
                    ->schema([
                        Forms\Components\Select::make('application_id')
                            ->label('Aplicação')
                            ->relationship('application', 'name')
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('commit_sha')
                            ->label('Commit SHA')
                            ->maxLength(40),

                        Forms\Components\Textarea::make('commit_message')
                            ->label('Mensagem do commit')
                            ->maxLength(500),

                        Forms\Components\TextInput::make('commit_author')
                            ->label('Autor')
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->options(DeploymentStatus::class)
                            ->default('pending')
                            ->required(),

                        Forms\Components\TextInput::make('triggered_by')
                            ->label('Disparado por')
                            ->default('manual'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Logs de build')
                    ->schema([
                        Forms\Components\Textarea::make('build_logs')
                            ->label('Logs')
                            ->rows(20)
                            ->disabled(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('application.name')
                    ->label('Aplicação')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('short_commit_sha')
                    ->label('Commit')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('commit_message')
                    ->label('Mensagem')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->commit_message),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Disparado por')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duração'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Iniciado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DeploymentStatus::class),

                Tables\Filters\SelectFilter::make('application_id')
                    ->relationship('application', 'name')
                    ->label('Aplicação'),
            ])
            ->actions([
                Tables\Actions\Action::make('logs')
                    ->label('Ver logs')
                    ->icon('heroicon-o-document-text')
                    ->modalContent(fn (Deployment $record) => view('filament.modals.deployment-logs', ['deployment' => $record]))
                    ->modalWidth('5xl'),

                Tables\Actions\Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Deployment $record) => $record->isRunning())
                    ->action(fn (Deployment $record) => static::triggerRollback($record)),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Deployment $record) => $record->isActive())
                    ->action(fn (Deployment $record) => $record->update(['status' => DeploymentStatus::Cancelled])),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeployments::route('/'),
            'view' => Pages\ViewDeployment::route('/{record}'),
        ];
    }

    protected static function triggerRollback(Deployment $record): void
    {
        // TODO: Implement rollback via OrchestratorClient
    }
}
