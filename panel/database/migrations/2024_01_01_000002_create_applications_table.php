<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');                             // nodejs, php, golang, python, static, docker
            $table->string('git_repository')->nullable();
            $table->string('git_branch')->default('main');
            $table->string('git_token')->nullable();            // Encrypted, for private repos
            $table->string('build_command')->nullable();
            $table->string('start_command')->nullable();
            $table->string('root_directory')->default('/');
            $table->integer('port')->default(3000);
            $table->integer('replicas')->default(1);
            $table->integer('min_replicas')->default(1);
            $table->integer('max_replicas')->default(5);
            $table->boolean('auto_deploy')->default(true);
            $table->boolean('auto_scale')->default(false);
            $table->integer('cpu_limit')->default(1000);        // millicores
            $table->integer('memory_limit')->default(512);      // MB
            $table->string('status')->default('stopped');       // active, stopped, deploying, failed
            $table->json('health_check')->nullable();           // {"path": "/health", "interval": 30}
            $table->string('webhook_secret')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
