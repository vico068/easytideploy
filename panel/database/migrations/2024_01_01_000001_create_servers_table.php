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
            $table->string('hostname');
            $table->ipAddress('ip_address');
            $table->ipAddress('internal_ip')->nullable();
            $table->integer('agent_port')->default(9090);
            $table->string('status')->default('offline'); // online, offline, maintenance, draining
            $table->integer('max_containers')->default(50);
            $table->integer('cpu_total')->default(8000);        // millicores
            $table->integer('memory_total')->default(32768);    // MB
            $table->integer('cpu_used')->default(0);
            $table->integer('memory_used')->default(0);
            $table->json('labels')->nullable();                 // {"region": "br-1", "tier": "standard"}
            $table->timestamp('last_heartbeat_at')->nullable();
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
