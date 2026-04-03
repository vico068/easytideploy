<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->uuid('container_id')->nullable();
            $table->string('level')->default('info');           // debug, info, warning, error, critical
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();

            $table->index(['application_id', 'timestamp']);
            $table->index(['application_id', 'level']);
            $table->index('container_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_logs');
    }
};
