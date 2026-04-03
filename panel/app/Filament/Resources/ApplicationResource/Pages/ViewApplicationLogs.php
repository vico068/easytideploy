<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\LogStreamService;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Response;

class ViewApplicationLogs extends Page
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.view-application-logs';

    public Application $record;

    public string $selectedContainer = '';
    public string $logLevel = '';
    public string $searchQuery = '';
    public bool $autoRefresh = false;
    public int $maxLines = 500;

    public function mount(Application $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return "Logs - {$this->record->name}";
    }

    public function getLogs()
    {
        $query = $this->record->logs()->latest('timestamp');

        if ($this->selectedContainer) {
            $query->where('container_id', $this->selectedContainer);
        }

        if ($this->logLevel) {
            $query->where('level', $this->logLevel);
        }

        if ($this->searchQuery) {
            $query->where('message', 'like', "%{$this->searchQuery}%");
        }

        return $query->limit($this->maxLines)->get()->reverse()->values();
    }

    public function getLogStats(): array
    {
        $since = now()->subHour();
        $baseQuery = $this->record->logs()->where('timestamp', '>=', $since);

        return [
            'total' => (clone $baseQuery)->count(),
            'errors' => (clone $baseQuery)->where('level', 'error')->count(),
            'warnings' => (clone $baseQuery)->where('level', 'warning')->count(),
            'criticals' => (clone $baseQuery)->where('level', 'critical')->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Voltar')
                ->url(ApplicationResource::getUrl('edit', ['record' => $this->record]))
                ->color('gray'),

            \Filament\Actions\Action::make('refresh')
                ->label('Atualizar')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('refresh-logs')),

            \Filament\Actions\Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->downloadLogs()),
        ];
    }

    public function downloadLogs()
    {
        $logService = app(LogStreamService::class);

        $content = $logService->downloadLogs(
            $this->record,
            $this->selectedContainer ?: null,
            $this->logLevel ?: null,
        );

        $filename = sprintf(
            'logs-%s-%s.txt',
            $this->record->slug,
            now()->format('Y-m-d-His')
        );

        return Response::streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    protected function getViewData(): array
    {
        return [
            'logs' => $this->getLogs(),
            'stats' => $this->getLogStats(),
        ];
    }
}
