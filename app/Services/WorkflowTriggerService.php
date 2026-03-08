<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowRun;
use App\Models\User;
use App\Jobs\ExecuteWorkflowRunJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service to match system events to active workflows and dispatch runs.
 *
 * This is the bridge between domain events (stock received, order approved, etc.)
 * and the workflow engine. When an event occurs, this service finds all active
 * workflows with matching trigger types and dispatches runs for each.
 */
class WorkflowTriggerService
{
    public function __construct(
        protected WorkflowEngineService $engine,
    ) {}

    /**
     * Fire a trigger event across all matching active workflows.
     *
     * @param  string  $triggerType  e.g. 'low_stock_reached', 'order_approved'
     * @param  array   $payload      Context data from the triggering event
     * @param  int|null $userId      The user who caused the event (if any)
     * @param  bool    $async        Whether to dispatch as queue job
     * @return array<int, WorkflowRun>  Runs that were created
     */
    public function fire(string $triggerType, array $payload = [], ?int $userId = null, bool $async = true): array
    {
        $workflows = $this->findMatchingWorkflows($triggerType, $payload);

        if ($workflows->isEmpty()) {
            return [];
        }

        $runs = [];

        foreach ($workflows as $workflow) {
            try {
                $run = $this->createRunForWorkflow($workflow, $triggerType, $payload, $userId);

                if ($run) {
                    if ($async) {
                        ExecuteWorkflowRunJob::dispatch($run->id);
                    } else {
                        $this->engine->executeRun($run);
                        $run->refresh();
                    }
                    $runs[] = $run;
                }
            } catch (\Throwable $e) {
                Log::error('WorkflowTriggerService: failed to dispatch run', [
                    'trigger_type' => $triggerType,
                    'workflow_id' => $workflow->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $runs;
    }

    /**
     * Find all active workflows whose published version has a trigger node
     * matching the given trigger type.
     */
    protected function findMatchingWorkflows(string $triggerType, array $payload = [])
    {
        $workflowIds = DB::table('workflow_definitions as wd')
            ->join('workflow_versions as wv', function ($join) {
                $join->on('wd.id', '=', 'wv.workflow_definition_id')
                     ->where('wv.status', '=', 'published');
            })
            ->join('workflow_nodes as wn', function ($join) use ($triggerType) {
                $join->on('wv.id', '=', 'wn.workflow_version_id')
                     ->where('wn.type', '=', 'trigger')
                     ->where('wn.action_type', '=', $triggerType);
            })
            ->whereNull('wd.deleted_at')
            ->where('wd.status', 'active')
            ->distinct()
            ->pluck('wd.id');

        return WorkflowDefinition::whereIn('id', $workflowIds)->get();
    }

    /**
     * Create a pending run for a workflow, respecting concurrency limits.
     */
    protected function createRunForWorkflow(
        WorkflowDefinition $workflow,
        string $triggerType,
        array $payload,
        ?int $userId
    ): ?WorkflowRun {
        return DB::transaction(function () use ($workflow, $triggerType, $payload, $userId) {
            $lockedWf = WorkflowDefinition::query()
                ->whereKey($workflow->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedWf || $lockedWf->status !== 'active') {
                return null;
            }

            // Check concurrency
            $activeRuns = WorkflowRun::where('workflow_definition_id', $lockedWf->id)
                ->whereIn('status', ['pending', 'running'])
                ->lockForUpdate()
                ->count();

            if ($activeRuns >= $lockedWf->max_concurrency) {
                Log::warning('WorkflowTriggerService: concurrency limit reached', [
                    'workflow_id' => $lockedWf->id,
                    'active_runs' => $activeRuns,
                    'max' => $lockedWf->max_concurrency,
                ]);
                return null;
            }

            $version = WorkflowVersion::where('workflow_definition_id', $lockedWf->id)
                ->where('status', 'published')
                ->orderByDesc('version_number')
                ->with('nodes')
                ->first();

            if (!$version) {
                return null;
            }

            // Check trigger config matching and persist matched trigger config to run context.
            $matchedTriggerConfig = $this->resolveMatchingTriggerConfig($version, $triggerType, $payload);
            if ($matchedTriggerConfig === null) {
                return null;
            }

            $runContext = $payload;
            if (!empty($matchedTriggerConfig)) {
                $runContext['_workflow_trigger_config'] = $matchedTriggerConfig;
            }

            return WorkflowRun::create([
                'workflow_definition_id' => $lockedWf->id,
                'workflow_version_id' => $version->id,
                'status' => 'pending',
                'trigger_type' => $triggerType,
                'trigger_payload' => $payload,
                'context' => $runContext,
                'triggered_by' => $this->resolveTriggeringUserId($userId),
                'is_dry_run' => false,
                'idempotency_key' => Str::uuid()->toString(),
            ]);
        }, 5);
    }

    /**
     * Check if the trigger node's config constraints match the payload.
     * For example, a low_stock_reached trigger with threshold=10 should only fire
     * when the payload quantity is below 10.
     */
    protected function triggerConfigMatches(WorkflowVersion $version, string $triggerType, array $payload): bool
    {
        return $this->resolveMatchingTriggerConfig($version, $triggerType, $payload) !== null;
    }

    /**
     * Resolve the matching trigger configuration for the payload.
     * Returns null when no trigger node for the given type matches.
     */
    protected function resolveMatchingTriggerConfig(WorkflowVersion $version, string $triggerType, array $payload): ?array
    {
        $triggerNodes = $version->nodes()
            ->where('type', 'trigger')
            ->where('action_type', $triggerType)
            ->get();

        if ($triggerNodes->isEmpty()) {
            return false;
        }

        foreach ($triggerNodes as $node) {
            $config = $node->config ?? [];

            switch ($triggerType) {
                case 'low_stock_reached':
                    $threshold = $config['threshold'] ?? null;
                    $qty = $payload['quantity'] ?? $payload['available_qty'] ?? null;
                    if ($threshold !== null && $qty !== null && (int) $qty >= (int) $threshold) {
                        continue 2; // This trigger node doesn't match
                    }
                    return is_array($config) ? $config : [];

                case 'expiry_in_x_days':
                    $days = $config['days'] ?? null;
                    $expiryDate = $payload['expiry_date'] ?? null;
                    if ($days !== null && $expiryDate !== null) {
                        try {
                            $daysUntilExpiry = now()->diffInDays(\Carbon\Carbon::parse($expiryDate), false);
                            if ($daysUntilExpiry < 0 || $daysUntilExpiry > (int) $days) {
                                continue 2;
                            }
                        } catch (\Throwable) {
                            continue 2;
                        }
                    }
                    return is_array($config) ? $config : [];

                case 'stock_received':
                    $configProductId = $config['product_id'] ?? null;
                    $configBranchId = $config['branch_id'] ?? null;
                    if ($configProductId && ($payload['product_id'] ?? null) != $configProductId) {
                        continue 2;
                    }
                    if ($configBranchId && ($payload['branch_id'] ?? null) != $configBranchId) {
                        continue 2;
                    }
                    return is_array($config) ? $config : [];

                case 'daily_schedule':
                    $configuredCron = trim((string) ($config['cron'] ?? ''));
                    $payloadCron = trim((string) ($payload['cron'] ?? ''));
                    if ($configuredCron !== '' && $payloadCron !== '' && $configuredCron !== $payloadCron) {
                        continue 2;
                    }
                    return is_array($config) ? $config : [];

                default:
                    // For triggers without specific config matching, always match
                    return is_array($config) ? $config : [];
            }
        }

        return null;
    }

    /**
     * Fire the daily_schedule trigger for all matching workflows.
     * Called by the scheduler command.
     */
    public function fireScheduledWorkflows(): array
    {
        $workflowIds = DB::table('workflow_definitions as wd')
            ->join('workflow_versions as wv', function ($join) {
                $join->on('wd.id', '=', 'wv.workflow_definition_id')
                     ->where('wv.status', '=', 'published');
            })
            ->join('workflow_nodes as wn', function ($join) {
                $join->on('wv.id', '=', 'wn.workflow_version_id')
                     ->where('wn.type', '=', 'trigger')
                     ->where('wn.action_type', '=', 'daily_schedule');
            })
            ->whereNull('wd.deleted_at')
            ->where('wd.status', 'active')
            ->distinct()
            ->pluck('wd.id');

        $workflows = WorkflowDefinition::whereIn('id', $workflowIds)->get();
        $runs = [];

        foreach ($workflows as $workflow) {
            $version = $workflow->versions()
                ->where('status', 'published')
                ->latest('version_number')
                ->first();

            if (!$version) {
                continue;
            }

            // Check cron expression matches current time
            $triggerNodes = $version->nodes()
                ->where('type', 'trigger')
                ->where('action_type', 'daily_schedule')
                ->get();

            foreach ($triggerNodes as $node) {
                $cron = $node->config['cron'] ?? null;
                if ($cron && $this->cronMatchesNow($cron)) {
                    try {
                        $run = $this->createRunForWorkflow($workflow, 'daily_schedule', [
                            'scheduled_at' => now()->toIso8601String(),
                            'cron' => $cron,
                        ], null);

                        if ($run) {
                            ExecuteWorkflowRunJob::dispatch($run->id);
                            $runs[] = $run;
                        }
                    } catch (\Throwable $e) {
                        Log::error('WorkflowTriggerService: failed to dispatch scheduled run', [
                            'workflow_id' => $workflow->id,
                            'cron' => $cron,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    break; // One run per workflow
                }
            }
        }

        return $runs;
    }

    protected function resolveTriggeringUserId(?int $userId): ?int
    {
        if ($userId !== null && User::query()->whereKey($userId)->exists()) {
            return $userId;
        }

        $fallbackUserId = User::query()->orderBy('id')->value('id');
        return $fallbackUserId ? (int) $fallbackUserId : null;
    }

    /**
     * Simple cron expression check against current time.
     * Supports: minute hour day month weekday
     */
    protected function cronMatchesNow(string $cron): bool
    {
        $parts = preg_split('/\s+/', trim($cron));
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;
        $now = now();

        return $this->cronFieldMatches($minute, $now->minute)
            && $this->cronFieldMatches($hour, $now->hour)
            && $this->cronFieldMatches($day, $now->day)
            && $this->cronFieldMatches($month, $now->month)
            && $this->cronFieldMatches($weekday, $now->dayOfWeekIso % 7);
    }

    protected function cronFieldMatches(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        // Handle ranges: 1-5
        if (str_contains($field, '-')) {
            [$start, $end] = explode('-', $field, 2);
            return $value >= (int) $start && $value <= (int) $end;
        }

        // Handle lists: 1,3,5
        if (str_contains($field, ',')) {
            $values = array_map('intval', explode(',', $field));
            return in_array($value, $values);
        }

        // Handle step: */5
        if (str_starts_with($field, '*/')) {
            $step = (int) substr($field, 2);
            return $step > 0 && ($value % $step) === 0;
        }

        return (int) $field === $value;
    }
}
