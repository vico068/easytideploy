<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_variables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value');                              // Encrypted at rest
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_build_time')->default(false);   // Disponível no build
            $table->timestamps();

            $table->unique(['application_id', 'key']);
            $table->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_variables');
    }
};
