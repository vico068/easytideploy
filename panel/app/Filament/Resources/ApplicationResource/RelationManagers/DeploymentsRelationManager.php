<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Enums\DeploymentStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DeploymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'deployments';

    protected static ?string $title = 'Deployments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('short_commit_sha')
                    ->label('Commit')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('commit_message')
                    ->label('Mensagem')
                    ->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('Disparado por')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duração'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(DeploymentStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('logs')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->modalContent(fn ($record) => view('filament.modals.deployment-logs', ['deployment' => $record]))
                    ->modalWidth('5xl'),

                Tables\Actions\Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->isRunning()),
            ]);
    }
}
