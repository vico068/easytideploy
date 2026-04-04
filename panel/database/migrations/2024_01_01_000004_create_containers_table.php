<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deployment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->string('docker_container_id')->nullable();  // Docker container ID
            $table->string('name');                             // container name
            $table->ipAddress('internal_ip')->nullable();
            $table->integer('internal_port')->default(0);
            $table->integer('host_port')->nullable();
            $table->integer('replica_index')->default(0);
            $table->string('status')->default('starting');      // starting, running, stopping, stopped, failed, unhealthy, pending
            $table->string('health_status')->default('unknown'); // healthy, unhealthy, unknown
            $table->decimal('cpu_usage', 5, 2)->default(0);
            $table->decimal('memory_usage', 5, 2)->default(0);
            $table->integer('restart_count')->default(0);
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['server_id', 'status']);
            $table->index(['deployment_id', 'status']);
            $table->index('docker_container_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
