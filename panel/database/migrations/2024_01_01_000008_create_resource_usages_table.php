<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('container_id')->nullable();
            $table->uuid('server_id')->nullable();
            $table->decimal('cpu_percent', 8, 2)->default(0);
            $table->decimal('memory_percent', 8, 2)->default(0);
            $table->decimal('disk_percent', 8, 2)->default(0);
            $table->decimal('cpu_usage', 5, 2)->default(0);
            $table->decimal('memory_usage', 5, 2)->default(0);
            $table->bigInteger('network_in')->default(0);       // bytes received
            $table->bigInteger('network_out')->default(0);      // bytes transmitted
            $table->bigInteger('network_rx')->default(0);       // alias
            $table->bigInteger('network_tx')->default(0);       // alias
            $table->bigInteger('disk_read')->default(0);        // bytes read
            $table->bigInteger('disk_write')->default(0);       // bytes written
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['application_id', 'recorded_at']);
            $table->index(['container_id', 'recorded_at']);
            $table->index(['server_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_usages');
    }
};
