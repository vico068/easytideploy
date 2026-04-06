<?php

namespace App\Filament\Pages;

use App\Models\Deployment;
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

    public bool $isActive = false;

    public function mount(string $deploymentId): void
    {
        $this->deploymentId = $deploymentId;
        $this->deployment = Deployment::with('application')->findOrFail($deploymentId);
        $this->isActive = $this->deployment->isActive();
    }

    public function refreshStatus(): void
    {
        $this->deployment->refresh();
        $this->isActive = $this->deployment->isActive();

        if (! $this->isActive) {
            $this->dispatch('deployment-finished');
        }
    }

    public function getTitle(): string
    {
        $appName = $this->deployment?->application?->name ?? 'Deploy';

        return "Deploy ao vivo — {$appName}";
    }
}
