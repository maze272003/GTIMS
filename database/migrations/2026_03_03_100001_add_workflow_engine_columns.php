<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add retry/dead-letter columns to workflow_runs
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->unsignedInteger('retry_attempt')->default(0)->after('is_dry_run');
            $table->unsignedInteger('max_retries')->default(3)->after('retry_attempt');
            $table->timestamp('next_retry_at')->nullable()->after('max_retries');
            $table->boolean('is_dead_letter')->default(false)->after('next_retry_at');
            $table->foreignId('parent_run_id')->nullable()->after('is_dead_letter')
                  ->constrained('workflow_runs')->nullOnDelete();
            $table->index('is_dead_letter');
            $table->index('next_retry_at');
        });

        // Add max_retries + retry_backoff to workflow_run_steps
        Schema::table('workflow_run_steps', function (Blueprint $table) {
            $table->unsignedInteger('max_retries')->default(3)->after('retry_count');
            $table->timestamp('next_retry_at')->nullable()->after('max_retries');
        });

        // Add webhook_allowlist to workflow_definitions
        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->json('webhook_allowlist')->nullable()->after('max_concurrency');
            $table->string('webhook_secret', 64)->nullable()->after('webhook_allowlist');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropForeign(['parent_run_id']);
            $table->dropIndex(['is_dead_letter']);
            $table->dropIndex(['next_retry_at']);
            $table->dropColumn(['retry_attempt', 'max_retries', 'next_retry_at', 'is_dead_letter', 'parent_run_id']);
        });

        Schema::table('workflow_run_steps', function (Blueprint $table) {
            $table->dropColumn(['max_retries', 'next_retry_at']);
        });

        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->dropColumn(['webhook_allowlist', 'webhook_secret']);
        });
    }
};
