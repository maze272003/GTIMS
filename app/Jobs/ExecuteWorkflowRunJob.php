<?php

namespace App\Jobs;

use App\Models\WorkflowRun;
use App\Services\WorkflowEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteWorkflowRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Max attempts = 1 because the engine handles its own retry logic.
     */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $runId,
    ) {
        $this->onQueue('workflows');
    }

    public function handle(WorkflowEngineService $engine): void
    {
        $run = WorkflowRun::find($this->runId);

        if (!$run) {
            Log::warning('ExecuteWorkflowRunJob: run not found', ['run_id' => $this->runId]);
            return;
        }

        if (!in_array($run->status, ['pending', 'running'])) {
            Log::info('ExecuteWorkflowRunJob: run already resolved', [
                'run_id' => $this->runId,
                'status' => $run->status,
            ]);
            return;
        }

        try {
            $engine->executeRun($run);
        } catch (\Throwable $e) {
            Log::error('ExecuteWorkflowRunJob failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);

            // Let engine's retry/dead-letter logic handle it
            $engine->handleFailedRun($run, $e);
        }
    }

    public function tags(): array
    {
        return ['workflow', "run:{$this->runId}"];
    }
}
