<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->string('node_id');
            $table->string('action_type');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'skipped'])->default('pending');
            $table->json('input_snapshot')->nullable();
            $table->json('output_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_run_id', 'node_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_run_steps');
    }
};
