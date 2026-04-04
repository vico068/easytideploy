<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('hostname')->nullable();
            $table->ipAddress('ip_address');
            $table->ipAddress('internal_ip')->nullable();
            $table->string('agent_address')->nullable();            // ip:port for gRPC
            $table->integer('agent_port')->default(9090);
            $table->string('status')->default('offline'); // online, offline, maintenance, draining
            $table->integer('max_containers')->default(50);
            $table->integer('cpu_cores')->default(0);               // actual CPU cores
            $table->integer('cpu_total')->default(8000);            // millicores capacity
            $table->bigInteger('memory_total')->default(32768);     // bytes (from agent)
            $table->bigInteger('disk_total')->default(0);           // bytes
            $table->integer('cpu_used')->default(0);
            $table->integer('memory_used')->default(0);
            $table->string('docker_version')->nullable();
            $table->json('labels')->nullable();                 // {"region": "br-1", "tier": "standard"}
            $table->timestamp('last_heartbeat')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'cpu_used', 'memory_used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
