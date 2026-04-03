<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Deployment;

class DeploymentObserver
{
    public function created(Deployment $deployment): void
    {
        $appName = $deployment->application?->name ?? 'N/A';
        $commit = $deployment->commit_sha ? substr($deployment->commit_sha, 0, 7) : 'N/A';

        ActivityLog::log(
            ActivityLog::ACTION_DEPLOY,
            "Deploy iniciado para '{$appName}' (commit: {$commit})",
            $deployment,
            [
                'application_id' => $deployment->application_id,
                'commit_sha' => $deployment->commit_sha,
                'triggered_by' => $deployment->triggered_by,
            ],
        );
    }

    public function updated(Deployment $deployment): void
    {
        $changes = $deployment->getChanges();

        if (!isset($changes['status'])) {
            return;
        }

        $appName = $deployment->application?->name ?? 'N/A';

        $description = match ($changes['status']) {
            'building' => "Build iniciado para '{$appName}'",
            'deploying' => "Deploy em progresso para '{$appName}'",
            'running' => "Deploy concluído com sucesso para '{$appName}'",
            'failed' => "Deploy falhou para '{$appName}'",
            'cancelled' => "Deploy cancelado para '{$appName}'",
            'rolled_back' => "Rollback realizado para '{$appName}'",
            default => "Deploy para '{$appName}' atualizado (status: {$changes['status']})",
        };

        $action = match ($changes['status']) {
            'rolled_back' => ActivityLog::ACTION_ROLLBACK,
            default => ActivityLog::ACTION_DEPLOY,
        };

        ActivityLog::log(
            $action,
            $description,
            $deployment,
            ['status' => $changes['status']],
        );
    }
}
