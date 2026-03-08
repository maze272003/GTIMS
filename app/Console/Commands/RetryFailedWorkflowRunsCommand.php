<?php

namespace App\Console\Commands;

use App\Models\WorkflowRun;
use App\Jobs\ExecuteWorkflowRunJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedWorkflowRunsCommand extends Command
{
    protected $signature = 'workflows:retry-failed {--limit=20 : Max runs to retry per invocation}';

    protected $description = 'Retry failed workflow runs that have remaining retry attempts and are past their backoff window';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Checking for retryable failed workflow runs (limit: {$limit})...");

        $runs = WorkflowRun::retryable()
            ->with('definition')
            ->limit($limit)
            ->get();

        if ($runs->isEmpty()) {
            $this->info('No retryable runs found.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        $deadLettered = 0;

        foreach ($runs as $run) {
            $nextAttempt = $run->retry_attempt + 1;

            if ($nextAttempt > $run->max_retries) {
                // Move to dead-letter
                $run->update([
                    'is_dead_letter' => true,
                    'error_message' => ($run->error_message ?? '') . ' [Moved to dead-letter after ' . $run->max_retries . ' retries]',
                ]);
                $deadLettered++;
                $this->warn("  Run #{$run->id} moved to dead-letter (max retries reached)");

                Log::warning('Workflow run moved to dead-letter', [
                    'run_id' => $run->id,
                    'workflow_id' => $run->workflow_definition_id,
                    'retries' => $run->retry_attempt,
                ]);
                continue;
            }

            // Create a new retry run
            $retryRun = WorkflowRun::create([
                'workflow_definition_id' => $run->workflow_definition_id,
                'workflow_version_id' => $run->workflow_version_id,
                'status' => 'pending',
                'trigger_type' => $run->trigger_type,
                'trigger_payload' => $run->trigger_payload,
                'context' => $run->trigger_payload ?? [],
                'triggered_by' => $run->triggered_by,
                'is_dry_run' => $run->is_dry_run,
                'retry_attempt' => $nextAttempt,
                'max_retries' => $run->max_retries,
                'parent_run_id' => $run->parent_run_id ?? $run->id,
                'idempotency_key' => $run->idempotency_key . ':retry:' . $nextAttempt,
            ]);

            // Mark the old run as superseded
            $run->update([
                'error_message' => ($run->error_message ?? '') . " [Retried as run #{$retryRun->id}]",
            ]);

            ExecuteWorkflowRunJob::dispatch($retryRun->id);
            $dispatched++;
            $this->info("  Run #{$run->id} → Retry #{$retryRun->id} (attempt {$nextAttempt})");
        }

        $this->info("Done: {$dispatched} retried, {$deadLettered} dead-lettered.");
        return self::SUCCESS;
    }
}
