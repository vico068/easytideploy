<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('deploy')
                ->label('Deploy')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->triggerDeploy()),

            Actions\Action::make('stop')
                ->label('Parar')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isActive())
                ->action(fn () => $this->stopApplication()),

            Actions\Action::make('restart')
                ->label('Reiniciar')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isActive())
                ->action(fn () => $this->restartApplication()),

            Actions\DeleteAction::make(),
        ];
    }

    protected function triggerDeploy(): void
    {
        // TODO: Implement via OrchestratorClient
        Notification::make()
            ->success()
            ->title('Deploy iniciado com sucesso!')
            ->send();
    }

    protected function stopApplication(): void
    {
        // TODO: Implement via OrchestratorClient
        Notification::make()
            ->success()
            ->title('Aplicação parada com sucesso!')
            ->send();
    }

    protected function restartApplication(): void
    {
        // TODO: Implement via OrchestratorClient
        Notification::make()
            ->success()
            ->title('Aplicação reiniciada com sucesso!')
            ->send();
    }
}
