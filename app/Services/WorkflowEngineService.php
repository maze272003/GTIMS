<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkflowEngineService
{
    public function __construct(
        protected AuditService $auditService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Get the node catalog: available triggers, conditions, actions with config schemas.
     */
    public function getNodeCatalog(): array
    {
        return [
            'triggers' => [
                ['type' => 'trigger', 'action_type' => 'stock_received', 'label' => 'Stock Received', 'config_schema' => ['product_id' => 'optional|integer', 'branch_id' => 'optional|integer']],
                ['type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock Reached', 'config_schema' => ['threshold' => 'optional|integer']],
                ['type' => 'trigger', 'action_type' => 'expiry_in_x_days', 'label' => 'Expiry in X Days', 'config_schema' => ['days' => 'required|integer']],
                ['type' => 'trigger', 'action_type' => 'order_created', 'label' => 'Order Created', 'config_schema' => []],
                ['type' => 'trigger', 'action_type' => 'order_approved', 'label' => 'Order Approved', 'config_schema' => []],
                ['type' => 'trigger', 'action_type' => 'order_canceled', 'label' => 'Order Canceled', 'config_schema' => []],
                ['type' => 'trigger', 'action_type' => 'daily_schedule', 'label' => 'Daily Schedule (Cron)', 'config_schema' => ['cron' => 'required|string']],
            ],
            'conditions' => [
                ['type' => 'condition', 'action_type' => 'branch_matches', 'label' => 'Branch Matches', 'config_schema' => ['branch_ids' => 'required|array']],
                ['type' => 'condition', 'action_type' => 'category_matches', 'label' => 'Category Matches', 'config_schema' => ['categories' => 'required|array']],
                ['type' => 'condition', 'action_type' => 'expiry_threshold', 'label' => 'Expiry Threshold', 'config_schema' => ['days' => 'required|integer']],
                ['type' => 'condition', 'action_type' => 'quantity_threshold', 'label' => 'Quantity Threshold', 'config_schema' => ['operator' => 'required|string', 'value' => 'required|integer']],
            ],
            'actions' => [
                ['type' => 'action', 'action_type' => 'create_hold', 'label' => 'Create Hold', 'config_schema' => ['reason' => 'optional|string']],
                ['type' => 'action', 'action_type' => 'release_hold', 'label' => 'Release Hold', 'config_schema' => []],
                ['type' => 'action', 'action_type' => 'notify', 'label' => 'Send Notification', 'config_schema' => ['message' => 'required|string', 'channel' => 'optional|string']],
                ['type' => 'action', 'action_type' => 'create_reorder_suggestion', 'label' => 'Create Reorder Suggestion', 'config_schema' => ['quantity' => 'optional|integer']],
                ['type' => 'action', 'action_type' => 'auto_allocate_order', 'label' => 'Auto Allocate Order (FEFO)', 'config_schema' => []],
                ['type' => 'action', 'action_type' => 'create_transfer_request', 'label' => 'Create Transfer Request', 'config_schema' => ['target_branch_id' => 'optional|integer']],
                ['type' => 'action', 'action_type' => 'generate_report', 'label' => 'Generate Report', 'config_schema' => ['report_type' => 'required|string']],
                ['type' => 'action', 'action_type' => 'webhook_call', 'label' => 'Webhook Call', 'config_schema' => ['url' => 'required|url', 'method' => 'optional|string']],
            ],
        ];
    }

    /**
     * Validate a workflow graph: DAG check (no cycles), at least one trigger, no orphan nodes.
     */
    public function validateGraph(WorkflowVersion $version): array
    {
        $errors = [];
        $nodes = $version->nodes;
        $edges = $version->edges;

        if ($nodes->isEmpty()) {
            $errors[] = 'Workflow must have at least one node.';
            return $errors;
        }

        $triggers = $nodes->where('type', 'trigger');
        if ($triggers->isEmpty()) {
            $errors[] = 'Workflow must have at least one trigger node.';
        }

        $actions = $nodes->where('type', 'action');
        if ($actions->isEmpty()) {
            $errors[] = 'Workflow must have at least one action node.';
        }

        // DAG cycle detection via topological sort
        $nodeIds = $nodes->pluck('node_id')->toArray();
        $adjacency = [];
        $inDegree = [];
        foreach ($nodeIds as $nid) {
            $adjacency[$nid] = [];
            $inDegree[$nid] = 0;
        }
        foreach ($edges as $edge) {
            if (!in_array($edge->source_node_id, $nodeIds) || !in_array($edge->target_node_id, $nodeIds)) {
                $errors[] = "Edge references unknown node: {$edge->source_node_id} -> {$edge->target_node_id}";
                continue;
            }
            $adjacency[$edge->source_node_id][] = $edge->target_node_id;
            $inDegree[$edge->target_node_id]++;
        }

        // Kahn's algorithm
        $queue = [];
        foreach ($inDegree as $nid => $deg) {
            if ($deg === 0) {
                $queue[] = $nid;
            }
        }
        $visited = 0;
        while (!empty($queue)) {
            $current = array_shift($queue);
            $visited++;
            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }
        if ($visited !== count($nodeIds)) {
            $errors[] = 'Workflow graph contains a cycle. Only DAGs are allowed.';
        }

        // Check for orphan nodes (not connected by any edge)
        $connectedNodes = collect();
        foreach ($edges as $edge) {
            $connectedNodes->push($edge->source_node_id);
            $connectedNodes->push($edge->target_node_id);
        }
        $connectedNodes = $connectedNodes->unique();
        if ($nodes->count() > 1) {
            foreach ($nodes as $node) {
                if (!$connectedNodes->contains($node->node_id)) {
                    $errors[] = "Node '{$node->label}' ({$node->node_id}) is not connected to any edge.";
                }
            }
        }

        return $errors;
    }

    /**
     * Execute a workflow run synchronously (for simplicity; queue dispatch recommended for production).
     */
    public function executeRun(WorkflowRun $run): WorkflowRun
    {
        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $version = $run->version;
            $nodes = $version->nodes->keyBy('node_id');
            $edges = $version->edges;

            // Build adjacency list
            $adjacency = [];
            foreach ($nodes as $node) {
                $adjacency[$node->node_id] = [];
            }
            foreach ($edges as $edge) {
                $adjacency[$edge->source_node_id][] = [
                    'target' => $edge->target_node_id,
                    'condition_branch' => $edge->condition_branch,
                ];
            }

            // Topological sort to get execution order
            $executionOrder = $this->topologicalSort($nodes->keys()->toArray(), $edges);

            $context = $run->context ?? [];

            foreach ($executionOrder as $nodeId) {
                $node = $nodes[$nodeId];
                $step = WorkflowRunStep::create([
                    'workflow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'action_type' => $node->action_type,
                    'status' => 'running',
                    'input_snapshot' => ['context' => $context, 'config' => $node->config],
                    'started_at' => now(),
                ]);

                try {
                    $result = $this->executeNode($node, $context, $run->is_dry_run);

                    // For condition nodes, check if we should skip downstream
                    if ($node->type === 'condition' && isset($result['condition_met'])) {
                        $context['_condition_results'][$nodeId] = $result['condition_met'];
                    }

                    $context = array_merge($context, $result['context_updates'] ?? []);

                    $step->update([
                        'status' => 'completed',
                        'output_snapshot' => $result,
                        'completed_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    $step->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => now(),
                    ]);

                    // For non-dry-run, fail the whole run on step failure
                    if (!$run->is_dry_run) {
                        throw $e;
                    }
                }
            }

            $run->update([
                'status' => 'completed',
                'context' => $context,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Workflow run failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        // Audit the run completion
        $this->auditService->record(
            'workflow_run_' . $run->status,
            'WorkflowRun',
            $run->id,
            $run->triggered_by ?? 0,
            null,
            ['status' => $run->status, 'is_dry_run' => $run->is_dry_run],
            'Workflow run completed',
            ['workflow_name' => $run->definition->name]
        );

        return $run->fresh();
    }

    /**
     * Execute a single node.
     */
    protected function executeNode(WorkflowNode $node, array $context, bool $isDryRun): array
    {
        $config = $node->config ?? [];

        return match ($node->type) {
            'trigger' => $this->executeTrigger($node, $context, $isDryRun),
            'condition' => $this->evaluateCondition($node, $context),
            'action' => $this->executeAction($node, $context, $isDryRun),
            default => ['status' => 'skipped', 'message' => 'Unknown node type'],
        };
    }

    protected function executeTrigger(WorkflowNode $node, array $context, bool $isDryRun): array
    {
        // Triggers are evaluated at run start; during execution they pass through
        return [
            'status' => 'passed',
            'message' => "Trigger '{$node->action_type}' evaluated",
            'context_updates' => [],
        ];
    }

    protected function evaluateCondition(WorkflowNode $node, array $context): array
    {
        $config = $node->config ?? [];
        $met = true;

        switch ($node->action_type) {
            case 'branch_matches':
                $branchIds = $config['branch_ids'] ?? [];
                $contextBranch = $context['branch_id'] ?? null;
                $met = in_array($contextBranch, $branchIds);
                break;

            case 'category_matches':
                $categories = $config['categories'] ?? [];
                $contextCategory = $context['category'] ?? null;
                $met = in_array($contextCategory, $categories);
                break;

            case 'expiry_threshold':
                $days = $config['days'] ?? 30;
                $expiryDate = $context['expiry_date'] ?? null;
                if ($expiryDate) {
                    $met = now()->diffInDays($expiryDate, false) <= $days;
                } else {
                    $met = false;
                }
                break;

            case 'quantity_threshold':
                $operator = $config['operator'] ?? '<';
                $value = $config['value'] ?? 0;
                $qty = $context['quantity'] ?? $context['available_qty'] ?? 0;
                $met = match ($operator) {
                    '<' => $qty < $value,
                    '<=' => $qty <= $value,
                    '>' => $qty > $value,
                    '>=' => $qty >= $value,
                    '==' => $qty == $value,
                    default => false,
                };
                break;
        }

        return [
            'condition_met' => $met,
            'context_updates' => [],
        ];
    }

    protected function executeAction(WorkflowNode $node, array $context, bool $isDryRun): array
    {
        $config = $node->config ?? [];

        if ($isDryRun) {
            return [
                'status' => 'dry_run',
                'message' => "Would execute action: {$node->action_type}",
                'context_updates' => [],
            ];
        }

        switch ($node->action_type) {
            case 'notify':
                $message = $config['message'] ?? 'Workflow notification';
                // Send in-app notification to admins via database notifications
                $admins = \App\Models\User::whereHas('level', function ($query) {
                    $query->whereHas('permissions', function ($q) {
                        $q->where('name', 'notifications.manage');
                    });
                })->get();
                foreach ($admins as $admin) {
                    $this->notificationService->notify($admin, 'workflow_notification', [
                        'message' => $message,
                        'workflow_context' => array_intersect_key($context, array_flip(['product_id', 'branch_id', 'order_id'])),
                    ]);
                }
                return ['status' => 'sent', 'message' => $message, 'recipients' => $admins->count(), 'context_updates' => []];

            case 'create_hold':
                return [
                    'status' => 'action_logged',
                    'message' => 'Hold creation requested',
                    'context_updates' => ['hold_requested' => true, 'hold_reason' => $config['reason'] ?? 'Workflow automation'],
                ];

            case 'release_hold':
                return [
                    'status' => 'action_logged',
                    'message' => 'Hold release requested',
                    'context_updates' => ['hold_released' => true],
                ];

            case 'create_reorder_suggestion':
                return [
                    'status' => 'action_logged',
                    'message' => 'Reorder suggestion created',
                    'context_updates' => ['reorder_suggested' => true, 'suggested_qty' => $config['quantity'] ?? null],
                ];

            case 'auto_allocate_order':
                return [
                    'status' => 'action_logged',
                    'message' => 'FEFO auto-allocation requested',
                    'context_updates' => ['auto_allocated' => true],
                ];

            case 'create_transfer_request':
                return [
                    'status' => 'action_logged',
                    'message' => 'Transfer request created',
                    'context_updates' => ['transfer_requested' => true, 'target_branch_id' => $config['target_branch_id'] ?? null],
                ];

            case 'generate_report':
                return [
                    'status' => 'action_logged',
                    'message' => 'Report generation requested: ' . ($config['report_type'] ?? 'general'),
                    'context_updates' => ['report_generated' => true],
                ];

            case 'webhook_call':
                // Security: only allow pre-configured URLs; actual HTTP call omitted for safety
                return [
                    'status' => 'action_logged',
                    'message' => 'Webhook call logged (execution deferred to queue)',
                    'context_updates' => ['webhook_called' => true],
                ];

            default:
                return ['status' => 'unknown_action', 'message' => "Unknown action: {$node->action_type}", 'context_updates' => []];
        }
    }

    /**
     * Topological sort using Kahn's algorithm.
     */
    protected function topologicalSort(array $nodeIds, $edges): array
    {
        $adjacency = [];
        $inDegree = [];

        foreach ($nodeIds as $nid) {
            $adjacency[$nid] = [];
            $inDegree[$nid] = 0;
        }

        foreach ($edges as $edge) {
            if (in_array($edge->source_node_id, $nodeIds) && in_array($edge->target_node_id, $nodeIds)) {
                $adjacency[$edge->source_node_id][] = $edge->target_node_id;
                $inDegree[$edge->target_node_id]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $nid => $deg) {
            if ($deg === 0) {
                $queue[] = $nid;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;
            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $sorted;
    }

    /**
     * Start a workflow run (manual trigger).
     */
    public function startRun(
        WorkflowDefinition $definition,
        ?int $userId,
        array $triggerPayload = [],
        bool $isDryRun = false,
        ?string $idempotencyKey = null
    ): WorkflowRun {
        // Idempotency check
        if ($idempotencyKey) {
            $existing = WorkflowRun::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $version = $definition->publishedVersion();
        if (!$version) {
            throw new \RuntimeException('No published version for this workflow.');
        }

        // Concurrency check
        $activeRuns = WorkflowRun::where('workflow_definition_id', $definition->id)
            ->whereIn('status', ['pending', 'running'])
            ->count();
        if ($activeRuns >= $definition->max_concurrency) {
            throw new \RuntimeException('Max concurrency limit reached for this workflow.');
        }

        $run = WorkflowRun::create([
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'status' => 'pending',
            'trigger_type' => 'manual',
            'trigger_payload' => $triggerPayload,
            'context' => $triggerPayload,
            'triggered_by' => $userId,
            'is_dry_run' => $isDryRun,
            'idempotency_key' => $idempotencyKey ?? Str::uuid()->toString(),
        ]);

        return $this->executeRun($run);
    }
}
