<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.list-applications';

    public string $viewMode = 'cards';

    public string $userId = '';

    public function mount(): void
    {
        parent::mount();
        $this->userId = auth()->id() ?? '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova Aplicação')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getApplications()
    {
        return Application::with(['latestDeployment', 'containers'])
            ->withCount('containers')
            ->orderByDesc('updated_at')
            ->get();
    }

    /** Recebe mudança de status de deployment → refresh da lista */
    #[On('echo-private:user.{userId},DeploymentStatusChanged')]
    public function onDeploymentStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }

    /** Recebe mudança de status de container → refresh da lista */
    #[On('echo-private:user.{userId},ContainerStatusChanged')]
    public function onContainerStatusChanged(array $event): void
    {
        $this->dispatch('$refresh');
    }
}
