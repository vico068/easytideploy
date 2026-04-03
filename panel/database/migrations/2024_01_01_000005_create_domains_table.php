<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('ssl_enabled')->default(true);
            $table->text('ssl_certificate')->nullable();
            $table->text('ssl_private_key')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('ssl_status')->default('pending');   // pending, active, expired, failed
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
