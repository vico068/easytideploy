<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->text('git_repository')->nullable()->change();
            $table->text('git_token')->nullable()->change();
            $table->text('build_command')->nullable()->change();
            $table->text('start_command')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('git_repository')->nullable()->change();
            $table->string('git_token')->nullable()->change();
            $table->string('build_command')->nullable()->change();
            $table->string('start_command')->nullable()->change();
        });
    }
};
