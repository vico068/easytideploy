<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Orchestrator Configuration
    |--------------------------------------------------------------------------
    */

    'orchestrator_url' => env('EASYDEPLOY_ORCHESTRATOR_URL', 'http://localhost:8080'),

    'orchestrator_api_key' => env('EASYDEPLOY_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Environment Variables Encryption
    |--------------------------------------------------------------------------
    */

    'env_encryption_key' => env('ENV_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Application Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'replicas' => 1,
        'min_replicas' => 1,
        'max_replicas' => 5,
        'cpu_limit' => 1000, // millicores (1 CPU)
        'memory_limit' => 512, // MB
        'port' => 3000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Limits by Plan
    |--------------------------------------------------------------------------
    */

    'plans' => [
        'free' => [
            'max_applications' => 3,
            'max_containers_per_app' => 1,
            'cpu_limit' => 500,
            'memory_limit' => 256,
        ],
        'starter' => [
            'max_applications' => 10,
            'max_containers_per_app' => 3,
            'cpu_limit' => 1000,
            'memory_limit' => 512,
        ],
        'pro' => [
            'max_applications' => 50,
            'max_containers_per_app' => 10,
            'cpu_limit' => 2000,
            'memory_limit' => 2048,
        ],
        'enterprise' => [
            'max_applications' => -1, // unlimited
            'max_containers_per_app' => -1,
            'cpu_limit' => 8000,
            'memory_limit' => 16384,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'collection_interval' => 60, // seconds
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs Configuration
    |--------------------------------------------------------------------------
    */

    'logs' => [
        'retention_days' => 7,
        'max_lines_per_request' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    */

    'deployment' => [
        'timeout' => 600, // seconds (10 minutes)
        'max_retries' => 3,
        'keep_history' => 50, // deployments per application
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    */

    'domain' => [
        'default_suffix' => env('DEFAULT_DOMAIN_SUFFIX', 'easyti.cloud'),
        'ssl_enabled' => true,
        'ssl_provider' => 'letsencrypt',
    ],

];
