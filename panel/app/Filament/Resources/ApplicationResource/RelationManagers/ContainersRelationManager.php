<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Enums\ContainerStatus;
use App\Services\OrchestratorClient;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContainersRelationManager extends RelationManager
{
    protected static string $relationship = 'containers';

    protected static ?string $title = 'Containers';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('status', ContainerStatus::Running->value))
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                Tables\Columns\TextColumn::make('server.name')
                    ->label('Servidor'),

                Tables\Columns\TextColumn::make('short_container_id')
                    ->label('Container ID')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),

                Tables\Columns\TextColumn::make('health_status')
                    ->label('Saúde')
                    ->badge(),

                Tables\Columns\TextColumn::make('cpu_usage')
                    ->label('CPU')
                    ->suffix('%')
                    ->color(fn ($state) => $state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('memory_usage')
                    ->label('Memória')
                    ->suffix('%')
                    ->color(fn ($state) => $state > 80 ? 'danger' : ($state > 60 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('uptime')
                    ->label('Uptime'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ContainerStatus::class),

                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Servidor'),
            ])
            ->actions([
                Tables\Actions\Action::make('logs')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->url(fn ($record) => route('filament.admin.resources.applications.logs', [
                        'record' => $record->application_id,
                        'container' => $record->id,
                    ])),

                Tables\Actions\Action::make('restart')
                    ->label('Reiniciar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        try {
                            app(OrchestratorClient::class)->restartContainer($record->id);

                            Notification::make()
                                ->success()
                                ->title('Container reiniciado')
                                ->body('O container foi enviado para reinicializacao.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Falha ao reiniciar container')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->isRunning()),

                Tables\Actions\Action::make('stop')
                    ->label('Parar')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Ao parar um container em aplicacao ativa, o orquestrador iniciara outro automaticamente para manter as replicas configuradas.')
                    ->action(function ($record): void {
                        try {
                            app(OrchestratorClient::class)->stopContainer($record->id);

                            Notification::make()
                                ->success()
                                ->title('Container parado')
                                ->body('Parada solicitada. O orquestrador fara a reposicao automatica se necessario.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Falha ao parar container')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->isRunning()),
            ]);
    }

    public function onContainerStatusChanged(array $event): void
    {
        $this->resetTable();
    }

    protected function getListeners(): array
    {
        $applicationId = (string) ($this->ownerRecord?->id ?? '');
        if ($applicationId === '') {
            return [
                'container-status-changed' => 'onContainerStatusChanged',
            ];
        }

        return [
            'container-status-changed' => 'onContainerStatusChanged',
            "echo-private:application.{$applicationId},ContainerStatusChanged" => 'onContainerStatusChanged',
        ];
    }
}
