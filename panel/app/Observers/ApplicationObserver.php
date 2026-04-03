<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Application;

class ApplicationObserver
{
    public function created(Application $application): void
    {
        ActivityLog::log(
            ActivityLog::ACTION_CREATE,
            "Aplicação '{$application->name}' criada",
            $application,
            ['type' => $application->type->value, 'slug' => $application->slug],
        );
    }

    public function updated(Application $application): void
    {
        $changes = $application->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        // Special status-based logging
        if (isset($changes['status'])) {
            $description = match ($changes['status']) {
                'active' => "Aplicação '{$application->name}' ativada",
                'stopped' => "Aplicação '{$application->name}' parada",
                'deploying' => "Aplicação '{$application->name}' em deploy",
                'failed' => "Aplicação '{$application->name}' falhou",
                default => "Aplicação '{$application->name}' atualizada (status: {$changes['status']})",
            };
        } else {
            $description = "Aplicação '{$application->name}' atualizada";
        }

        ActivityLog::log(
            ActivityLog::ACTION_UPDATE,
            $description,
            $application,
            ['changes' => $changes],
        );
    }

    public function deleted(Application $application): void
    {
        ActivityLog::log(
            ActivityLog::ACTION_DELETE,
            "Aplicação '{$application->name}' excluída",
            $application,
        );
    }
}
