<?php

namespace App\Filament\Resources\DeploymentResource\Pages;

use App\Filament\Pages\DeploymentLive;
use App\Filament\Resources\DeploymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDeployment extends ViewRecord
{
    protected static string $resource = DeploymentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->isActive()) {
            $this->redirect(DeploymentLive::getUrl(['deploymentId' => $this->record->id]));
        }
    }
}
