<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use App\Enums\ContainerStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class ContainersRelationManager extends RelationManager
{
    protected static string $relationship = 'containers';

    protected static ?string $title = 'Containers';

    /** Application ID para o canal WebSocket — preenchido no mount() */
    public string $applicationId = '';

    public function mount(): void
    {
        parent::mount();
        $this->applicationId = $this->ownerRecord->id ?? '';
    }

    public function table(Table $table): Table
    {
        return $table
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
                    ->visible(fn ($record) => $record->isRunning()),

                Tables\Actions\Action::make('stop')
                    ->label('Parar')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->isRunning()),
            ]);
    }

    /** Atualiza tabela quando status/saude dos containers mudar em tempo real */
    #[On('echo-private:application.{applicationId},ContainerStatusChanged')]
    public function onContainerStatusChanged(array $event): void
    {
        $this->resetTable();
    }
}
