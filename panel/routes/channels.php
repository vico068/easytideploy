<?php

use Illuminate\Support\Facades\Broadcast;

// broadcasting/auth precisa do grupo 'web' para StartSession (leitura da sessão/usuário)
// CSRF excluído em bootstrap/app.php pois pusher-js não envia X-XSRF-TOKEN
Broadcast::routes(['middleware' => ['web', 'auth:web']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('deployment.{deploymentId}', function ($user, $deploymentId) {
    $deployment = \App\Models\Deployment::with('application')->find($deploymentId);

    return $deployment && $deployment->application->user_id === $user->id;
});

Broadcast::channel('application.{applicationId}', function ($user, $applicationId) {
    $app = \App\Models\Application::find($applicationId);

    return $app && $app->user_id === $user->id;
});

// User-level channel for dashboard, lists and widgets — receives all events for the user
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});
