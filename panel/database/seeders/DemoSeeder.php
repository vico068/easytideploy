<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\ContainerStatus;
use App\Enums\DeploymentStatus;
use App\Enums\HealthStatus;
use App\Enums\LogLevel;
use App\Enums\ServerStatus;
use App\Enums\SslStatus;
use App\Models\Application;
use App\Models\ApplicationLog;
use App\Models\Container;
use App\Models\Deployment;
use App\Models\Domain;
use App\Models\EnvironmentVariable;
use App\Models\ResourceUsage;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@easyti.cloud'],
            [
                'name' => 'Admin EasyDeploy',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created: admin@easyti.cloud / password');

        // Create servers
        $servers = [
            [
                'name' => 'Servidor Principal',
                'hostname' => 'srv-01.easyti.cloud',
                'ip_address' => '10.0.1.10',
                'agent_port' => 9090,
                'status' => ServerStatus::Online,
                'max_containers' => 50,
                'cpu_cores' => 8,
                'memory_total' => 16384,
                'disk_total' => 500000,
                'cpu_usage' => 35.5,
                'memory_usage' => 52.3,
                'disk_usage' => 28.7,
                'last_heartbeat' => now(),
            ],
            [
                'name' => 'Servidor Secundário',
                'hostname' => 'srv-02.easyti.cloud',
                'ip_address' => '10.0.1.11',
                'agent_port' => 9090,
                'status' => ServerStatus::Online,
                'max_containers' => 50,
                'cpu_cores' => 4,
                'memory_total' => 8192,
                'disk_total' => 250000,
                'cpu_usage' => 22.1,
                'memory_usage' => 41.8,
                'disk_usage' => 15.2,
                'last_heartbeat' => now(),
            ],
            [
                'name' => 'Servidor Staging',
                'hostname' => 'srv-stg.easyti.cloud',
                'ip_address' => '10.0.2.10',
                'agent_port' => 9090,
                'status' => ServerStatus::Online,
                'max_containers' => 20,
                'cpu_cores' => 2,
                'memory_total' => 4096,
                'disk_total' => 100000,
                'cpu_usage' => 12.0,
                'memory_usage' => 35.5,
                'disk_usage' => 10.1,
                'last_heartbeat' => now()->subMinutes(2),
            ],
        ];

        $createdServers = collect();
        foreach ($servers as $serverData) {
            $createdServers->push(Server::create($serverData));
        }

        $this->command->info('Created ' . $createdServers->count() . ' servers');

        // Create applications
        $apps = [
            [
                'name' => 'API Backend',
                'slug' => 'api-backend',
                'type' => ApplicationType::NodeJS,
                'status' => ApplicationStatus::Active,
                'user_id' => $admin->id,
                'git_repository' => 'https://github.com/easyti/api-backend.git',
                'git_branch' => 'main',
                'build_command' => 'npm ci && npm run build',
                'start_command' => 'node dist/server.js',
                'port' => 3000,
                'replicas' => 2,
                'cpu_limit' => 500,
                'memory_limit' => 512,
                'auto_deploy' => true,
                'health_check_path' => '/health',
                'health_check_interval' => 30,
            ],
            [
                'name' => 'Website Frontend',
                'slug' => 'website-frontend',
                'type' => ApplicationType::Static,
                'status' => ApplicationStatus::Active,
                'user_id' => $admin->id,
                'git_repository' => 'https://github.com/easyti/website.git',
                'git_branch' => 'main',
                'build_command' => 'npm ci && npm run build',
                'start_command' => 'npx serve -s build -l 3000',
                'port' => 3000,
                'replicas' => 1,
                'cpu_limit' => 250,
                'memory_limit' => 256,
                'auto_deploy' => true,
            ],
            [
                'name' => 'Worker Service',
                'slug' => 'worker-service',
                'type' => ApplicationType::Golang,
                'status' => ApplicationStatus::Active,
                'user_id' => $admin->id,
                'git_repository' => 'https://github.com/easyti/worker.git',
                'git_branch' => 'main',
                'build_command' => 'go build -o /app/worker ./cmd/worker',
                'start_command' => '/app/worker',
                'port' => 8080,
                'replicas' => 1,
                'cpu_limit' => 1000,
                'memory_limit' => 1024,
                'auto_deploy' => false,
                'health_check_path' => '/healthz',
            ],
            [
                'name' => 'Admin Panel',
                'slug' => 'admin-panel',
                'type' => ApplicationType::PHP,
                'status' => ApplicationStatus::Active,
                'user_id' => $admin->id,
                'git_repository' => 'https://github.com/easyti/admin-panel.git',
                'git_branch' => 'main',
                'build_command' => 'composer install --no-dev',
                'start_command' => 'php artisan serve --host=0.0.0.0 --port=8000',
                'port' => 8000,
                'replicas' => 1,
                'cpu_limit' => 500,
                'memory_limit' => 512,
            ],
            [
                'name' => 'ML Pipeline',
                'slug' => 'ml-pipeline',
                'type' => ApplicationType::Python,
                'status' => ApplicationStatus::Stopped,
                'user_id' => $admin->id,
                'git_repository' => 'https://github.com/easyti/ml-pipeline.git',
                'git_branch' => 'develop',
                'build_command' => 'pip install -r requirements.txt',
                'start_command' => 'uvicorn main:app --host 0.0.0.0 --port 8000',
                'port' => 8000,
                'replicas' => 1,
                'cpu_limit' => 2000,
                'memory_limit' => 2048,
            ],
        ];

        $createdApps = collect();
        foreach ($apps as $appData) {
            $createdApps->push(Application::create($appData));
        }

        $this->command->info('Created ' . $createdApps->count() . ' applications');

        // Create domains
        $domains = [
            ['application_id' => $createdApps[0]->id, 'domain' => 'api.easyti.cloud', 'is_primary' => true, 'ssl_enabled' => true, 'ssl_status' => SslStatus::Active],
            ['application_id' => $createdApps[1]->id, 'domain' => 'www.easyti.cloud', 'is_primary' => true, 'ssl_enabled' => true, 'ssl_status' => SslStatus::Active],
            ['application_id' => $createdApps[1]->id, 'domain' => 'easyti.cloud', 'is_primary' => false, 'ssl_enabled' => true, 'ssl_status' => SslStatus::Active],
            ['application_id' => $createdApps[2]->id, 'domain' => 'worker.easyti.cloud', 'is_primary' => true, 'ssl_enabled' => true, 'ssl_status' => SslStatus::Active],
            ['application_id' => $createdApps[3]->id, 'domain' => 'admin.easyti.cloud', 'is_primary' => true, 'ssl_enabled' => true, 'ssl_status' => SslStatus::Active],
        ];

        foreach ($domains as $domainData) {
            Domain::create(array_merge($domainData, [
                'ssl_expires_at' => now()->addMonths(3),
            ]));
        }

        $this->command->info('Created domains');

        // Create environment variables
        foreach ($createdApps->take(4) as $app) {
            EnvironmentVariable::create(['application_id' => $app->id, 'key' => 'NODE_ENV', 'value' => 'production', 'is_build_time' => false]);
            EnvironmentVariable::create(['application_id' => $app->id, 'key' => 'DATABASE_URL', 'value' => 'postgres://app:secret@db:5432/app', 'is_build_time' => false]);
            EnvironmentVariable::create(['application_id' => $app->id, 'key' => 'REDIS_URL', 'value' => 'redis://redis:6379', 'is_build_time' => false]);
        }

        // Create deployments
        $activeApps = $createdApps->take(4);
        foreach ($activeApps as $app) {
            // Create 5 past deployments
            for ($i = 5; $i >= 1; $i--) {
                $status = $i === 1 ? DeploymentStatus::Running : ($i === 3 ? DeploymentStatus::Failed : DeploymentStatus::Running);
                Deployment::create([
                    'application_id' => $app->id,
                    'commit_sha' => Str::random(40),
                    'commit_message' => fake()->sentence(4),
                    'commit_author' => fake()->name(),
                    'status' => $status,
                    'triggered_by' => $i % 2 === 0 ? 'webhook' : 'manual',
                    'started_at' => now()->subDays($i)->subHours(rand(0, 12)),
                    'finished_at' => now()->subDays($i)->subHours(rand(0, 12))->addMinutes(rand(1, 5)),
                    'build_logs' => "Building {$app->name}...\nInstalling dependencies...\nBuild complete.",
                    'created_at' => now()->subDays($i),
                ]);
            }
        }

        $this->command->info('Created deployments');

        // Create containers
        foreach ($activeApps as $index => $app) {
            $server = $createdServers[$index % $createdServers->count()];
            $latestDeployment = $app->deployments()->latest()->first();

            for ($r = 0; $r < $app->replicas; $r++) {
                Container::create([
                    'application_id' => $app->id,
                    'deployment_id' => $latestDeployment?->id,
                    'server_id' => $server->id,
                    'container_id' => 'sha256:' . Str::random(64),
                    'container_name' => "{$app->slug}-{$r}",
                    'image' => "registry.easyti.cloud/{$app->slug}:latest",
                    'status' => ContainerStatus::Running,
                    'health_status' => HealthStatus::Healthy,
                    'host_port' => 30000 + ($index * 10) + $r,
                    'cpu_usage' => rand(5, 60) + (rand(0, 100) / 100),
                    'memory_usage' => rand(20, 70) + (rand(0, 100) / 100),
                    'started_at' => now()->subHours(rand(1, 72)),
                ]);
            }
        }

        $this->command->info('Created containers');

        // Create resource usages (last 24h)
        foreach ($activeApps as $app) {
            foreach ($app->containers as $container) {
                for ($h = 24; $h >= 0; $h--) {
                    ResourceUsage::create([
                        'application_id' => $app->id,
                        'container_id' => $container->id,
                        'cpu_usage' => rand(5, 80) + (rand(0, 100) / 100),
                        'memory_usage' => rand(20, 75) + (rand(0, 100) / 100),
                        'network_rx' => rand(100000, 5000000),
                        'network_tx' => rand(50000, 3000000),
                        'disk_read' => rand(10000, 500000),
                        'disk_write' => rand(10000, 300000),
                        'recorded_at' => now()->subHours($h),
                    ]);
                }
            }
        }

        $this->command->info('Created resource usage history');

        // Create application logs
        $logMessages = [
            [LogLevel::Info, 'Server started on port {port}'],
            [LogLevel::Info, 'Connected to database'],
            [LogLevel::Info, 'Redis connection established'],
            [LogLevel::Info, 'Health check passed'],
            [LogLevel::Debug, 'Processing request GET /api/users'],
            [LogLevel::Debug, 'Cache hit for key: user:123'],
            [LogLevel::Warning, 'Slow query detected (>500ms)'],
            [LogLevel::Warning, 'Rate limit approaching for IP 192.168.1.100'],
            [LogLevel::Error, 'Failed to connect to external API: timeout'],
            [LogLevel::Error, 'Unhandled promise rejection'],
            [LogLevel::Info, 'Deployment webhook received'],
            [LogLevel::Info, 'Graceful shutdown initiated'],
        ];

        foreach ($activeApps as $app) {
            $container = $app->containers->first();
            for ($i = 0; $i < 50; $i++) {
                [$level, $message] = $logMessages[array_rand($logMessages)];
                ApplicationLog::create([
                    'application_id' => $app->id,
                    'container_id' => $container?->id,
                    'level' => $level,
                    'message' => str_replace('{port}', (string) $app->port, $message),
                    'timestamp' => now()->subMinutes(rand(0, 720)),
                ]);
            }
        }

        $this->command->info('Created application logs');
        $this->command->info('Demo seeding complete!');
    }
}
