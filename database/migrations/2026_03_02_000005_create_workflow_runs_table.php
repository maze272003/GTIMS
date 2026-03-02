<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions');
            $table->foreignId('workflow_version_id')->constrained('workflow_versions');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('trigger_type')->nullable();
            $table->json('trigger_payload')->nullable();
            $table->json('context')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users');
            $table->boolean('is_dry_run')->default(false);
            $table->string('idempotency_key')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('idempotency_key');
            $table->index('status');
            $table->index('workflow_definition_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
