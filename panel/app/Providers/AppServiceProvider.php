<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\Deployment;
use App\Models\Server;
use App\Observers\ApplicationObserver;
use App\Observers\DeploymentObserver;
use App\Observers\ServerObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Application::observe(ApplicationObserver::class);
        Deployment::observe(DeploymentObserver::class);
        Server::observe(ServerObserver::class);
    }
}
