<?php

namespace App\Listeners;

use App\Events\DeploymentCompleted;
use App\Events\DeploymentFailed;
use App\Events\DeploymentStarted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendDeploymentNotification implements ShouldQueue
{
    public function handleDeploymentStarted(DeploymentStarted $event): void
    {
        $deployment = $event->deployment;
        $user = $deployment->application->user;

        // Could send notification via email, Slack, etc.
        logger()->info("Deployment started", [
            'deployment_id' => $deployment->id,
            'application' => $deployment->application->name,
            'user' => $user->email,
        ]);
    }

    public function handleDeploymentCompleted(DeploymentCompleted $event): void
    {
        $deployment = $event->deployment;
        $user = $deployment->application->user;

        logger()->info("Deployment completed", [
            'deployment_id' => $deployment->id,
            'application' => $deployment->application->name,
            'duration' => $deployment->duration,
        ]);

        // Mail::to($user)->send(new DeploymentSuccessMail($deployment));
    }

    public function handleDeploymentFailed(DeploymentFailed $event): void
    {
        $deployment = $event->deployment;
        $user = $deployment->application->user;

        logger()->error("Deployment failed", [
            'deployment_id' => $deployment->id,
            'application' => $deployment->application->name,
            'reason' => $event->reason,
        ]);

        // Mail::to($user)->send(new DeploymentFailedMail($deployment, $event->reason));
    }

    public function subscribe($events): array
    {
        return [
            DeploymentStarted::class => 'handleDeploymentStarted',
            DeploymentCompleted::class => 'handleDeploymentCompleted',
            DeploymentFailed::class => 'handleDeploymentFailed',
        ];
    }
}
