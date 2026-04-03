<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use Filament\Resources\Pages\Page;

class ViewApplicationMetrics extends Page
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.view-application-metrics';

    public Application $record;

    public string $period = '1h';

    public function mount(Application $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return "Métricas - {$this->record->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Voltar')
                ->url(ApplicationResource::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),
        ];
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }
}
