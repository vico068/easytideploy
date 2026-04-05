<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('http_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->integer('requests_2xx')->default(0);
            $table->integer('requests_3xx')->default(0);
            $table->integer('requests_4xx')->default(0);
            $table->integer('requests_5xx')->default(0);
            $table->integer('total_requests')->default(0);
            $table->decimal('avg_latency_ms', 10, 2)->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['application_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('http_metrics');
    }
};
