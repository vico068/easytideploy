<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use App\Models\Domain;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if (! $user->is_admin && ! $user->canCreateApplication()) {
            $limits = $user->getPlanLimits();

            Notification::make()
                ->title('Limite do plano atingido')
                ->body("Seu plano {$user->plan->getLabel()} permite no máximo {$limits['max_applications']} aplicações.")
                ->danger()
                ->send();

            $this->halt();
        }

        $data['user_id'] = $user->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $application = $this->record;
        $suffix = config('easydeploy.domain.default_suffix', 'apps.easyti.cloud');
        $defaultDomain = sprintf('%s.%s', $application->slug, $suffix);

        // Criar domínio principal automaticamente
        $application->domains()->create([
            'domain' => $defaultDomain,
            'is_primary' => true,
            'verified' => true,
            'ssl_status' => 'pending',
            'ssl_enabled' => true,
        ]);

        // Disparar primeiro deploy automaticamente se repositório Git está configurado
        if (! empty($application->git_repository)) {
            \App\Jobs\ProcessDeploymentJob::dispatch($application);

            \Filament\Notifications\Notification::make()
                ->title('Deploy iniciado')
                ->body("O primeiro deploy de \"{$application->name}\" foi iniciado automaticamente.")
                ->success()
                ->send();
        }
    }
}
