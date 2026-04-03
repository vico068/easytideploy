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
            $table->string('container_id');                     // Docker container ID
            $table->string('container_name');
            $table->ipAddress('internal_ip')->nullable();
            $table->integer('port');
            $table->string('status')->default('starting');      // starting, running, stopping, stopped, failed, unhealthy
            $table->string('health_status')->default('unknown'); // healthy, unhealthy, unknown
            $table->decimal('cpu_usage', 5, 2)->default(0);
            $table->decimal('memory_usage', 5, 2)->default(0);
            $table->integer('restart_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['server_id', 'status']);
            $table->index(['deployment_id', 'status']);
            $table->index('container_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
