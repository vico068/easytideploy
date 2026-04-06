<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('deployment.{deploymentId}', function ($user, $deploymentId) {
    $deployment = \App\Models\Deployment::with('application')->find($deploymentId);

    return $deployment && $deployment->application->user_id === $user->id;
});

Broadcast::channel('application.{applicationId}', function ($user, $applicationId) {
    $app = \App\Models\Application::find($applicationId);

    return $app && $app->user_id === $user->id;
});
