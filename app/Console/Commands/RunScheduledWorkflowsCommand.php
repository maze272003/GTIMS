<?php

namespace App\Console\Commands;

use App\Services\WorkflowTriggerService;
use Illuminate\Console\Command;

class RunScheduledWorkflowsCommand extends Command
{
    protected $signature = 'workflows:run-scheduled';

    protected $description = 'Fire all active workflows with daily_schedule triggers whose cron matches the current time';

    public function handle(WorkflowTriggerService $triggerService): int
    {
        $this->info('Checking for scheduled workflows...');

        $runs = $triggerService->fireScheduledWorkflows();

        if (empty($runs)) {
            $this->info('No scheduled workflows matched at this time.');
            return self::SUCCESS;
        }

        $this->info(count($runs) . ' workflow run(s) dispatched:');
        foreach ($runs as $run) {
            $this->line("  - Run #{$run->id} for workflow #{$run->workflow_definition_id} ({$run->status})");
        }

        return self::SUCCESS;
    }
}
