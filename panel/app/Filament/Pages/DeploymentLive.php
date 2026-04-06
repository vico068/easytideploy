<?php

namespace App\Filament\Pages;

use App\Models\Deployment;
use Livewire\Attributes\On;
use Filament\Pages\Page;

class DeploymentLive extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.deployment-live';

    public static function getRoutePath(): string
    {
        return '/deployments/{deploymentId}/live';
    }

    public string $deploymentId;

    public ?Deployment $deployment = null;

    public function mount(string $deploymentId): void
    {
        $this->deploymentId = $deploymentId;
        $this->deployment = Deployment::with('application')->findOrFail($deploymentId);
    }

    /** Recebe linha de log via WebSocket → repassa ao Alpine.js do terminal */
    #[On('echo-private:deployment.{deploymentId},BuildLogReceived')]
    public function onBuildLog(array $event): void
    {
        $this->dispatch('build-log-received', line: $event['line'] ?? '', stage: $event['stage'] ?? 'build');
    }

    /** Recebe transição de etapa via WebSocket → repassa ao stepper Alpine.js */
    #[On('echo-private:deployment.{deploymentId},DeploymentStageChanged')]
    public function onStageChanged(array $event): void
    {
        $this->dispatch('stage-update', stage: $event['stage'] ?? '', status: $event['status'] ?? '');
    }

    /** Recebe mudança de status final via WebSocket → atualiza badge e finaliza terminal */
    #[On('echo-private:deployment.{deploymentId},DeploymentStatusChanged')]
    public function onStatusChanged(array $event): void
    {
        $this->deployment->refresh();

        if ($this->deployment->isTerminal()) {
            $this->dispatch('deployment-finished');
        }
    }

    public function getTitle(): string
    {
        $appName = $this->deployment?->application?->name ?? 'Deploy';

        return "Deploy ao vivo — {$appName}";
    }
}
