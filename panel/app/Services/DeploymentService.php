<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DeploymentStatus;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentFailed;
use App\Events\DeploymentStarted;
use App\Models\Application;
use App\Models\Deployment;
use Illuminate\Support\Str;

class DeploymentService
{
    public function __construct(
        private OrchestratorClient $orchestrator
    ) {}

    public function trigger(Application $application, ?string $commitSha = null, string $triggeredBy = 'manual'): Deployment
    {
        // Create deployment record
        $deployment = $application->deployments()->create([
            'commit_sha' => $commitSha,
            'status' => DeploymentStatus::Pending,
            'triggered_by' => $triggeredBy,
        ]);

        // Update application status
        $application->update(['status' => ApplicationStatus::Deploying]);

        // Dispatch event
        event(new DeploymentStarted($deployment));

        // Call orchestrator
        try {
            $callbackUrl = url('/api/internal/deployments/' . $deployment->id . '/status');
            $result = $this->orchestrator->deploy($application, $commitSha, $callbackUrl);

            $deployment->update([
                'image_tag' => $result['image_tag'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->markAsFailed($deployment, $e->getMessage());
        }

        return $deployment;
    }

    public function cancel(Deployment $deployment): bool
    {
        if (! $deployment->isActive()) {
            return false;
        }

        try {
            $this->orchestrator->cancelDeployment($deployment->id);

            $deployment->update([
                'status' => DeploymentStatus::Cancelled,
                'completed_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function rollback(Application $application, Deployment $targetDeployment): Deployment
    {
        $newDeployment = $application->deployments()->create([
            'commit_sha' => $targetDeployment->commit_sha,
            'commit_message' => "Rollback to {$targetDeployment->short_commit_sha}",
            'status' => DeploymentStatus::Pending,
            'triggered_by' => 'rollback',
            'image_tag' => $targetDeployment->image_tag,
        ]);

        $application->update(['status' => ApplicationStatus::Deploying]);

        event(new DeploymentStarted($newDeployment));

        try {
            $this->orchestrator->rollback($application, $targetDeployment->id);
        } catch (\Exception $e) {
            $this->markAsFailed($newDeployment, $e->getMessage());
        }

        return $newDeployment;
    }

    public function markAsRunning(Deployment $deployment): void
    {
        $deployment->markAsRunning();
        $deployment->application->update(['status' => ApplicationStatus::Active]);

        event(new DeploymentCompleted($deployment));
    }

    public function markAsFailed(Deployment $deployment, string $reason): void
    {
        $deployment->markAsFailed($reason);
        $deployment->application->update(['status' => ApplicationStatus::Failed]);

        event(new DeploymentFailed($deployment, $reason));
    }

    public function getLatestSuccessfulDeployment(Application $application): ?Deployment
    {
        return $application->deployments()
            ->where('status', DeploymentStatus::Running)
            ->latest()
            ->first();
    }
}
