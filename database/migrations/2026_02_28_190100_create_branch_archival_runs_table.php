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
        Schema::create('branch_archival_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_branch_id')->constrained('branches');
            $table->foreignId('target_branch_id')->constrained('branches');
            $table->foreignId('initiated_by')->constrained('users');
            $table->enum('status', ['in_progress', 'completed', 'failed', 'rolled_back'])->default('in_progress');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('steps')->nullable();
            $table->json('before_metrics')->nullable();
            $table->json('after_metrics')->nullable();
            $table->string('before_checksum', 64)->nullable();
            $table->string('after_checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['source_branch_id', 'status']);
            $table->index(['target_branch_id', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_archival_runs');
    }
};

