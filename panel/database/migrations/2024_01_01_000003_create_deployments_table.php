<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('commit_sha')->nullable();
            $table->string('commit_message', 500)->nullable();
            $table->string('commit_author')->nullable();
            $table->string('status')->default('pending');       // pending, building, deploying, running, failed, cancelled, rolled_back
            $table->longText('build_logs')->nullable();
            $table->string('image_name')->nullable();           // easydeploy/app-slug
            $table->string('image_tag')->nullable();            // deployment-id or version
            $table->string('triggered_by')->default('manual');  // manual, webhook, api, rollback, auto_scale, retry
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('status');
            $table->index(['application_id', 'status']);
            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
