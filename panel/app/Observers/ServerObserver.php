<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Server;

class ServerObserver
{
    public function created(Server $server): void
    {
        ActivityLog::log(
            ActivityLog::ACTION_CREATE,
            "Servidor '{$server->name}' adicionado ({$server->ip_address})",
            $server,
            ['ip_address' => $server->ip_address, 'hostname' => $server->hostname],
        );
    }

    public function updated(Server $server): void
    {
        $changes = $server->getChanges();
        unset($changes['updated_at'], $changes['last_heartbeat'], $changes['cpu_usage'], $changes['memory_usage'], $changes['disk_usage']);

        if (empty($changes)) {
            return;
        }

        if (isset($changes['status'])) {
            $action = match ($changes['status']) {
                'maintenance' => ActivityLog::ACTION_SERVER_MAINTENANCE,
                'draining' => ActivityLog::ACTION_SERVER_DRAIN,
                default => ActivityLog::ACTION_UPDATE,
            };

            $description = match ($changes['status']) {
                'maintenance' => "Servidor '{$server->name}' colocado em manutenção",
                'draining' => "Servidor '{$server->name}' sendo drenado",
                'online' => "Servidor '{$server->name}' voltou online",
                'offline' => "Servidor '{$server->name}' ficou offline",
                default => "Servidor '{$server->name}' atualizado (status: {$changes['status']})",
            };
        } else {
            $action = ActivityLog::ACTION_UPDATE;
            $description = "Servidor '{$server->name}' atualizado";
        }

        ActivityLog::log(
            $action,
            $description,
            $server,
            ['changes' => $changes],
        );
    }

    public function deleted(Server $server): void
    {
        ActivityLog::log(
            ActivityLog::ACTION_DELETE,
            "Servidor '{$server->name}' removido",
            $server,
        );
    }
}
