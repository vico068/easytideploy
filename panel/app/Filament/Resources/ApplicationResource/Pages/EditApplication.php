<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Services\DeploymentService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.edit-application';

    public function getListeners(): array
    {
        $appId = $this->record?->id;
        if (! $appId) {
            return [];
        }

        return [
            // Echo events para atualização em tempo real
            "echo-private:application.{$appId},DeploymentStatusChanged" => 'refreshStatus',
        ];
    }

    public function refreshStatus(): void
    {
        // Recarrega o record do banco para pegar o status atualizado
        $this->record->refresh();
    }

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

            Actions\DeleteAction::make()
                ->successRedirectUrl(ApplicationResource::getUrl('index')),
        ];
    }

    protected function triggerDeploy(): void
    {
        try {
            $deploymentService = app(DeploymentService::class);
            $deployment = $deploymentService->trigger($this->record);

            // Avisa o DeploymentsRelationManager para recarregar a tabela imediatamente
            $this->dispatch('deployment-triggered');

            Notification::make()
                ->success()
                ->title('Deploy iniciado!')
                ->body("Deployment #{$deployment->id} enfileirado.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Erro ao iniciar deploy')
                ->body($e->getMessage())
                ->send();
        }
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
