<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.list-applications';

    public string $viewMode = 'cards';

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
}
