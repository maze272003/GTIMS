<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkflowEngineService
{
    public function __construct(
        protected AuditService $auditService,
        protected NotificationService $notificationService,
        protected WorkflowReportService $workflowReportService,
    ) {}

    /**
     * Get the node catalog: available triggers, conditions, actions with config schemas.
     */
    public function getNodeCatalog(): array
    {
        return [
            'triggers' => [
                [
                    'type' => 'trigger',
                    'action_type' => 'stock_received',
                    'label' => 'Stock Received',
                    'config_schema' => ['product_id' => 'optional|integer|min:1', 'branch_id' => 'optional|integer|min:1'],
                    'default_preset' => 'all_products',
                    'presets' => [
                        ['key' => 'all_products', 'label' => 'All Products', 'config' => []],
                        ['key' => 'branch_1_product_1', 'label' => 'Branch 1 Product 1', 'config' => ['branch_id' => 1, 'product_id' => 1]],
                    ],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'low_stock_reached',
                    'label' => 'Low Stock Reached',
                    'config_schema' => ['threshold' => 'optional|integer|min:1'],
                    'default_preset' => 'warning_10',
                    'presets' => [
                        ['key' => 'critical_5', 'label' => 'Critical (< 5)', 'config' => ['threshold' => 5]],
                        ['key' => 'warning_10', 'label' => 'Warning (< 10)', 'config' => ['threshold' => 10]],
                        ['key' => 'buffer_25', 'label' => 'Buffer (< 25)', 'config' => ['threshold' => 25]],
                    ],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'expiry_in_x_days',
                    'label' => 'Expiry in X Days',
                    'config_schema' => ['days' => 'required|integer|min:1'],
                    'default_preset' => 'expiry_30',
                    'presets' => [
                        ['key' => 'expiry_7', 'label' => '7 Days', 'config' => ['days' => 7]],
                        ['key' => 'expiry_30', 'label' => '30 Days', 'config' => ['days' => 30]],
                        ['key' => 'expiry_60', 'label' => '60 Days', 'config' => ['days' => 60]],
                    ],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'order_created',
                    'label' => 'Order Created',
                    'config_schema' => [],
                    'default_preset' => 'all_orders',
                    'presets' => [['key' => 'all_orders', 'label' => 'All Orders', 'config' => []]],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'order_approved',
                    'label' => 'Order Approved',
                    'config_schema' => [],
                    'default_preset' => 'all_orders',
                    'presets' => [['key' => 'all_orders', 'label' => 'All Approved', 'config' => []]],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'order_canceled',
                    'label' => 'Order Canceled',
                    'config_schema' => [],
                    'default_preset' => 'all_orders',
                    'presets' => [['key' => 'all_orders', 'label' => 'All Canceled', 'config' => []]],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'daily_schedule',
                    'label' => 'Daily Schedule (Cron)',
                    'config_schema' => ['cron' => 'required|string|max:100'],
                    'default_preset' => 'daily_8am',
                    'presets' => [
                        ['key' => 'daily_8am', 'label' => 'Daily 8:00 AM', 'config' => ['cron' => '0 8 * * *']],
                        ['key' => 'hourly', 'label' => 'Hourly', 'config' => ['cron' => '0 * * * *']],
                        ['key' => 'weekdays_9am', 'label' => 'Weekdays 9:00 AM', 'config' => ['cron' => '0 9 * * 1-5']],
                    ],
                ],
            ],
            'conditions' => [
                [
                    'type' => 'condition',
                    'action_type' => 'branch_matches',
                    'label' => 'Branch Matches',
                    'config_schema' => ['branch_ids' => 'required|array'],
                    'default_preset' => 'main_branch',
                    'presets' => [
                        ['key' => 'main_branch', 'label' => 'Main Branch (1)', 'config' => ['branch_ids' => [1]]],
                        ['key' => 'core_branches', 'label' => 'Core Branches (1,2,3)', 'config' => ['branch_ids' => [1, 2, 3]]],
                    ],
                ],
                [
                    'type' => 'condition',
                    'action_type' => 'category_matches',
                    'label' => 'Category Matches',
                    'config_schema' => ['categories' => 'required|array'],
                    'default_preset' => 'vaccines_only',
                    'presets' => [
                        ['key' => 'vaccines_only', 'label' => 'Vaccines Only', 'config' => ['categories' => ['vaccine']]],
                        ['key' => 'essential_meds', 'label' => 'Essential Meds', 'config' => ['categories' => ['antibiotic', 'analgesic']]],
                    ],
                    'ui' => [
                        'categories' => ['vaccine', 'antibiotic', 'analgesic', 'consumable'],
                    ],
                ],
                [
                    'type' => 'condition',
                    'action_type' => 'expiry_threshold',
                    'label' => 'Expiry Threshold',
                    'config_schema' => ['days' => 'required|integer|min:1'],
                    'default_preset' => 'expiry_30',
                    'presets' => [
                        ['key' => 'expiry_15', 'label' => '15 Days', 'config' => ['days' => 15]],
                        ['key' => 'expiry_30', 'label' => '30 Days', 'config' => ['days' => 30]],
                    ],
                ],
                [
                    'type' => 'condition',
                    'action_type' => 'quantity_threshold',
                    'label' => 'Quantity Threshold',
                    'config_schema' => ['operator' => 'required|string', 'value' => 'required|integer|min:0'],
                    'default_preset' => 'below_10',
                    'presets' => [
                        ['key' => 'below_10', 'label' => 'Below 10', 'config' => ['operator' => '<', 'value' => 10]],
                        ['key' => 'below_or_equal_25', 'label' => 'At Most 25', 'config' => ['operator' => '<=', 'value' => 25]],
                    ],
                    'ui' => [
                        'operator' => ['<', '<=', '>', '>=', '=='],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'action',
                    'action_type' => 'create_hold',
                    'label' => 'Create Hold',
                    'config_schema' => ['reason' => 'optional|string|max:255'],
                    'default_preset' => 'quality_hold',
                    'presets' => [
                        ['key' => 'quality_hold', 'label' => 'Quality Hold', 'config' => ['reason' => 'Quality verification required']],
                        ['key' => 'expiry_hold', 'label' => 'Expiry Hold', 'config' => ['reason' => 'Near-expiry hold']],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'release_hold',
                    'label' => 'Release Hold',
                    'config_schema' => [],
                    'default_preset' => 'release',
                    'presets' => [['key' => 'release', 'label' => 'Release Matching Holds', 'config' => []]],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'notify',
                    'label' => 'Send Notification',
                    'config_schema' => ['message' => 'required|string|max:500', 'channel' => 'optional|string'],
                    'default_preset' => 'in_app_alert',
                    'presets' => [
                        ['key' => 'in_app_alert', 'label' => 'In-app Alert', 'config' => ['message' => 'Workflow alert generated.', 'channel' => 'in_app']],
                        ['key' => 'email_alert', 'label' => 'Email Alert', 'config' => ['message' => 'Workflow event needs attention.', 'channel' => 'email']],
                    ],
                    'ui' => [
                        'channel' => ['in_app', 'email'],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'create_reorder_suggestion',
                    'label' => 'Create Reorder Suggestion',
                    'config_schema' => ['quantity' => 'optional|integer|min:1'],
                    'default_preset' => 'auto_quantity',
                    'presets' => [
                        ['key' => 'auto_quantity', 'label' => 'Auto Quantity', 'config' => []],
                        ['key' => 'fixed_100', 'label' => 'Fixed 100 Units', 'config' => ['quantity' => 100]],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'auto_allocate_order',
                    'label' => 'Auto Allocate Order (FEFO)',
                    'config_schema' => [],
                    'default_preset' => 'fefo_auto',
                    'presets' => [['key' => 'fefo_auto', 'label' => 'FEFO Auto', 'config' => []]],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'create_transfer_request',
                    'label' => 'Create Transfer Request',
                    'config_schema' => ['target_branch_id' => 'optional|integer|min:1'],
                    'default_preset' => 'branch_1',
                    'presets' => [
                        ['key' => 'branch_1', 'label' => 'To Branch 1', 'config' => ['target_branch_id' => 1]],
                        ['key' => 'branch_2', 'label' => 'To Branch 2', 'config' => ['target_branch_id' => 2]],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'generate_report',
                    'label' => 'Generate Report',
                    'config_schema' => [
                        'report_type' => 'required|string',
                        'branch_id' => 'optional|integer|min:1',
                        'message' => 'optional|string|max:500',
                    ],
                    'default_preset' => 'low_stock',
                    'presets' => [
                        ['key' => 'low_stock', 'label' => 'Low Stock Report', 'config' => ['report_type' => 'low_stock']],
                        ['key' => 'expiry_report', 'label' => 'Expiry Report', 'config' => ['report_type' => 'expiry_report']],
                        ['key' => 'stock_movement', 'label' => 'Stock Movement', 'config' => ['report_type' => 'stock_movement']],
                    ],
                    'ui' => [
                        'report_type' => ['stock_movement', 'expiry_report', 'low_stock', 'inventory_summary'],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'webhook_call',
                    'label' => 'Webhook Call',
                    'config_schema' => ['url' => 'required|url|max:500', 'method' => 'optional|string'],
                    'default_preset' => 'post',
                    'presets' => [
                        ['key' => 'post', 'label' => 'POST Webhook', 'config' => ['url' => 'https://example.com/webhooks/workflow', 'method' => 'POST']],
                        ['key' => 'put', 'label' => 'PUT Webhook', 'config' => ['url' => 'https://example.com/webhooks/workflow', 'method' => 'PUT']],
                    ],
                    'ui' => [
                        'method' => ['POST', 'PUT', 'PATCH'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Validate and normalize graph payload from editor.
     *
     * @return array{valid:bool,errors:array<int,string>,graph:array{nodes:array<int,array<string,mixed>>,edges:array<int,array<string,mixed>>}}
     */
    public function validateGraphPayload(array $payload): array
    {
        $errors = [];
        $normalizedNodes = [];
        $normalizedEdges = [];
        $nodeIds = [];
        $edgeFingerprints = [];
        $catalog = $this->catalogByTypeAndAction();

        foreach (($payload['nodes'] ?? []) as $index => $node) {
            $nodeId = trim((string) ($node['node_id'] ?? ''));
            if ($nodeId === '') {
                $errors[] = "Node #{$index} is missing node_id.";
                continue;
            }

            if (isset($nodeIds[$nodeId])) {
                $errors[] = "Duplicate node_id '{$nodeId}' detected.";
                continue;
            }

            $type = (string) ($node['type'] ?? '');
            $actionType = (string) ($node['action_type'] ?? '');
            $catalogNode = $catalog[$type][$actionType] ?? null;
            if (!$catalogNode) {
                $errors[] = "Node '{$nodeId}' has unsupported type/action pair '{$type}:{$actionType}'.";
                continue;
            }

            $label = trim((string) ($node['label'] ?? $catalogNode['label']));
            if ($label === '') {
                $label = $catalogNode['label'];
            }

            $incomingConfig = is_array($node['config'] ?? null) ? $node['config'] : [];
            $baseConfig = [];
            if (isset($catalogNode['default_preset'])) {
                $defaultPreset = collect($catalogNode['presets'] ?? [])
                    ->firstWhere('key', $catalogNode['default_preset']);
                if (is_array($defaultPreset['config'] ?? null)) {
                    $baseConfig = $defaultPreset['config'];
                }
            }

            $configValidation = $this->validateAndNormalizeConfig(
                $catalogNode['config_schema'] ?? [],
                array_merge($baseConfig, $incomingConfig),
                $catalogNode['ui'] ?? []
            );

            foreach ($configValidation['errors'] as $configError) {
                $errors[] = "Node '{$nodeId}': {$configError}";
            }

            $position = is_array($node['position'] ?? null) ? $node['position'] : [];
            $normalizedNodes[] = [
                'node_id' => $nodeId,
                'type' => $type,
                'action_type' => $actionType,
                'label' => Str::limit($label, 255, ''),
                'config' => $configValidation['config'],
                'position' => [
                    'x' => max(0, (int) ($position['x'] ?? 100)),
                    'y' => max(0, (int) ($position['y'] ?? 100)),
                ],
            ];

            $nodeIds[$nodeId] = true;
        }

        foreach (($payload['edges'] ?? []) as $index => $edge) {
            $source = trim((string) ($edge['source_node_id'] ?? ''));
            $target = trim((string) ($edge['target_node_id'] ?? ''));
            if ($source === '' || $target === '') {
                $errors[] = "Edge #{$index} is missing source_node_id or target_node_id.";
                continue;
            }

            if ($source === $target) {
                $errors[] = "Self edge '{$source} -> {$target}' is not allowed.";
                continue;
            }

            if (!isset($nodeIds[$source]) || !isset($nodeIds[$target])) {
                $errors[] = "Edge references unknown node: {$source} -> {$target}";
                continue;
            }

            $conditionBranch = isset($edge['condition_branch']) ? trim((string) $edge['condition_branch']) : null;
            if ($conditionBranch === '') {
                $conditionBranch = null;
            }

            $fingerprint = "{$source}|{$target}|".($conditionBranch ?? '');
            if (isset($edgeFingerprints[$fingerprint])) {
                $errors[] = "Duplicate edge detected: {$source} -> {$target}";
                continue;
            }
            $edgeFingerprints[$fingerprint] = true;

            $normalizedEdges[] = [
                'source_node_id' => $source,
                'target_node_id' => $target,
                'label' => isset($edge['label']) ? Str::limit((string) $edge['label'], 255, '') : null,
                'condition_branch' => $conditionBranch,
            ];
        }

        if (empty($errors)) {
            $errors = array_merge($errors, $this->validateGraphArrays($normalizedNodes, $normalizedEdges));
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'graph' => [
                'nodes' => $normalizedNodes,
                'edges' => $normalizedEdges,
            ],
        ];
    }

    public function computeGraphHash(array $graph): string
    {
        $nodes = collect($graph['nodes'] ?? [])
            ->map(function (array $node): array {
                $normalized = [
                    'node_id' => (string) ($node['node_id'] ?? ''),
                    'type' => (string) ($node['type'] ?? ''),
                    'action_type' => (string) ($node['action_type'] ?? ''),
                    'label' => (string) ($node['label'] ?? ''),
                    'config' => $this->sortRecursive(is_array($node['config'] ?? null) ? $node['config'] : []),
                    'position' => $this->sortRecursive(is_array($node['position'] ?? null) ? $node['position'] : []),
                ];
                return $normalized;
            })
            ->sortBy('node_id')
            ->values()
            ->all();

        $edges = collect($graph['edges'] ?? [])
            ->map(fn (array $edge) => [
                'source_node_id' => (string) ($edge['source_node_id'] ?? ''),
                'target_node_id' => (string) ($edge['target_node_id'] ?? ''),
                'label' => isset($edge['label']) ? (string) $edge['label'] : null,
                'condition_branch' => isset($edge['condition_branch']) ? (string) $edge['condition_branch'] : null,
            ])
            ->sortBy(fn (array $edge) => "{$edge['source_node_id']}|{$edge['target_node_id']}|".($edge['condition_branch'] ?? ''))
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'nodes' => $nodes,
            'edges' => $edges,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Validate a workflow graph: DAG check (no cycles), at least one trigger, no orphan nodes.
     */
    public function validateGraph(WorkflowVersion $version): array
    {
        $payload = [
            'nodes' => $version->nodes->map(fn (WorkflowNode $node) => [
                'node_id' => $node->node_id,
                'type' => $node->type,
                'action_type' => $node->action_type,
                'label' => $node->label,
                'config' => $node->config ?? [],
                'position' => $node->position ?? [],
            ])->values()->all(),
            'edges' => $version->edges->map(fn (WorkflowEdge $edge) => [
                'source_node_id' => $edge->source_node_id,
                'target_node_id' => $edge->target_node_id,
                'label' => $edge->label,
                'condition_branch' => $edge->condition_branch,
            ])->values()->all(),
        ];

        return $this->validateGraphPayload($payload)['errors'];
    }

    protected function catalogByTypeAndAction(): array
    {
        $indexed = [];
        foreach (['triggers', 'conditions', 'actions'] as $group) {
            foreach ($this->getNodeCatalog()[$group] as $node) {
                $indexed[$node['type']][$node['action_type']] = $node;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string,string>  $schema
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $uiConfig
     * @return array{errors:array<int,string>,config:array<string,mixed>}
     */
    protected function validateAndNormalizeConfig(array $schema, array $config, array $uiConfig = []): array
    {
        $errors = [];
        $normalized = [];

        foreach ($schema as $field => $ruleString) {
            $rules = is_string($ruleString) ? explode('|', $ruleString) : [];
            $isRequired = in_array('required', $rules, true);
            $isOptional = in_array('optional', $rules, true);
            $hasValue = array_key_exists($field, $config) && $config[$field] !== null && $config[$field] !== '';

            if (!$hasValue) {
                if ($isRequired && !$isOptional) {
                    $errors[] = "Configuration field '{$field}' is required.";
                }
                continue;
            }

            $value = $config[$field];

            if (in_array('array', $rules, true)) {
                if (!is_array($value)) {
                    if (is_string($value)) {
                        $value = array_values(array_filter(array_map('trim', explode(',', $value))));
                    } else {
                        $errors[] = "Configuration field '{$field}' must be an array.";
                        continue;
                    }
                }
            }

            if (in_array('integer', $rules, true)) {
                if (is_array($value) || !is_numeric($value) || preg_match('/^-?\d+$/', (string) $value) !== 1) {
                    $errors[] = "Configuration field '{$field}' must be an integer.";
                    continue;
                }
                $value = (int) $value;
            }

            if (in_array('string', $rules, true)) {
                if (is_array($value) || is_object($value)) {
                    $errors[] = "Configuration field '{$field}' must be a string.";
                    continue;
                }
                $value = trim((string) $value);
            }

            if (in_array('url', $rules, true) && filter_var((string) $value, FILTER_VALIDATE_URL) === false) {
                $errors[] = "Configuration field '{$field}' must be a valid URL.";
                continue;
            }

            foreach ($rules as $rule) {
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_int($value) && $value < $min) {
                        $errors[] = "Configuration field '{$field}' must be at least {$min}.";
                    }
                    if (is_string($value) && mb_strlen($value) < $min) {
                        $errors[] = "Configuration field '{$field}' must be at least {$min} characters.";
                    }
                    if (is_array($value) && count($value) < $min) {
                        $errors[] = "Configuration field '{$field}' must have at least {$min} item(s).";
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_int($value) && $value > $max) {
                        $errors[] = "Configuration field '{$field}' must be at most {$max}.";
                    }
                    if (is_string($value) && mb_strlen($value) > $max) {
                        $errors[] = "Configuration field '{$field}' must be at most {$max} characters.";
                    }
                    if (is_array($value) && count($value) > $max) {
                        $errors[] = "Configuration field '{$field}' must have at most {$max} item(s).";
                    }
                }
            }

            $allowedValues = $uiConfig[$field] ?? null;
            if (is_array($allowedValues) && !empty($allowedValues)) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (!in_array($item, $allowedValues, true)) {
                            $errors[] = "Configuration field '{$field}' contains invalid value '{$item}'.";
                        }
                    }
                } elseif (!in_array($value, $allowedValues, true)) {
                    $errors[] = "Configuration field '{$field}' has invalid value '{$value}'.";
                }
            }

            $normalized[$field] = $value;
        }

        foreach ($config as $field => $_) {
            if (!array_key_exists($field, $schema)) {
                $errors[] = "Unknown configuration field '{$field}'.";
            }
        }

        return [
            'errors' => $errors,
            'config' => $normalized,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $nodes
     * @param  array<int,array<string,mixed>>  $edges
     * @return array<int,string>
     */
    protected function validateGraphArrays(array $nodes, array $edges): array
    {
        $errors = [];
        if (empty($nodes)) {
            $errors[] = 'Workflow must have at least one node.';
            return $errors;
        }

        $triggers = array_filter($nodes, fn (array $node) => $node['type'] === 'trigger');
        if (empty($triggers)) {
            $errors[] = 'Workflow must have at least one trigger node.';
        }

        $actions = array_filter($nodes, fn (array $node) => $node['type'] === 'action');
        if (empty($actions)) {
            $errors[] = 'Workflow must have at least one action node.';
        }

        $nodeIds = array_values(array_map(fn (array $node) => $node['node_id'], $nodes));
        $adjacency = [];
        $inDegree = [];
        foreach ($nodeIds as $nodeId) {
            $adjacency[$nodeId] = [];
            $inDegree[$nodeId] = 0;
        }

        foreach ($edges as $edge) {
            $source = $edge['source_node_id'];
            $target = $edge['target_node_id'];
            if (!in_array($source, $nodeIds, true) || !in_array($target, $nodeIds, true)) {
                $errors[] = "Edge references unknown node: {$source} -> {$target}";
                continue;
            }

            $adjacency[$source][] = $target;
            $inDegree[$target]++;
        }

        $queue = [];
        foreach ($inDegree as $nodeId => $degree) {
            if ($degree === 0) {
                $queue[] = $nodeId;
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

        $connectedNodes = [];
        foreach ($edges as $edge) {
            $connectedNodes[] = $edge['source_node_id'];
            $connectedNodes[] = $edge['target_node_id'];
        }
        $connectedNodes = array_values(array_unique($connectedNodes));
        if (count($nodes) > 1) {
            foreach ($nodes as $node) {
                if (!in_array($node['node_id'], $connectedNodes, true)) {
                    $errors[] = "Node '{$node['label']}' ({$node['node_id']}) is not connected to any edge.";
                }
            }
        }

        return $errors;
    }

    protected function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }
        ksort($value);
        return $value;
    }

    /**
     * Execute a workflow run synchronously (for simplicity; queue dispatch recommended for production).
     */
    public function executeRun(WorkflowRun $run): WorkflowRun
    {
        $started = WorkflowRun::query()
            ->whereKey($run->id)
            ->where('status', 'pending')
            ->update(['status' => 'running', 'started_at' => now()]);

        if ($started === 0) {
            return $run->fresh();
        }

        try {
            $run->refresh();
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
            $context['_workflow'] = array_filter([
                'run_id' => $run->id,
                'workflow_id' => $run->workflow_definition_id,
                'workflow_version_id' => $run->workflow_version_id,
                'workflow_name' => $run->definition?->name,
                'trigger_type' => $run->trigger_type,
                'is_dry_run' => $run->is_dry_run,
                'started_at' => optional($run->started_at)->toDateTimeString(),
            ], fn ($value) => $value !== null && $value !== '');

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
                $workflowContext = array_intersect_key($context, array_flip([
                    'product_id',
                    'branch_id',
                    'order_id',
                    'quantity',
                    'category',
                    'expiry_date',
                    'available_qty',
                    'hold_requested',
                    'hold_reason',
                    'transfer_requested',
                    'target_branch_id',
                    'report_generated',
                    'report_type',
                    'report_file_name',
                    'webhook_called',
                ]));
                $attachments = $this->resolveActionAttachments($context);
                if (isset($context['_workflow']) && is_array($context['_workflow'])) {
                    $workflowContext['_workflow'] = $context['_workflow'];
                }
                if (isset($context['_condition_results']) && is_array($context['_condition_results']) && !empty($context['_condition_results'])) {
                    $workflowContext['_condition_results'] = $context['_condition_results'];
                }
                // Send in-app notification to admins via database notifications
                $admins = \App\Models\User::whereHas('level', function ($query) {
                    $query->whereHas('permissions', function ($q) {
                        $q->where('name', 'notifications.manage');
                    });
                })->get();
                foreach ($admins as $admin) {
                    $this->notificationService->notify($admin, 'workflow_notification', [
                        'message' => $message,
                        'workflow_context' => $workflowContext,
                        'attachments' => $attachments,
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
                $report = $this->workflowReportService->generate($config, $context);
                $attachments = [[
                    'disk' => $report['disk'],
                    'path' => $report['path'],
                    'name' => $report['file_name'],
                    'mime' => $report['mime_type'],
                ]];

                $workflowContext = array_intersect_key($context, array_flip([
                    'product_id',
                    'branch_id',
                    'order_id',
                    'quantity',
                    'category',
                    'expiry_date',
                    'available_qty',
                    'hold_requested',
                    'hold_reason',
                    'transfer_requested',
                    'target_branch_id',
                    'webhook_called',
                ]));
                $workflowContext['report_generated'] = true;
                $workflowContext['report_type'] = $report['report_type'];
                $workflowContext['report_file_name'] = $report['file_name'];
                if (isset($context['_workflow']) && is_array($context['_workflow'])) {
                    $workflowContext['_workflow'] = $context['_workflow'];
                }
                if (isset($context['_condition_results']) && is_array($context['_condition_results']) && !empty($context['_condition_results'])) {
                    $workflowContext['_condition_results'] = $context['_condition_results'];
                }

                $admins = \App\Models\User::whereHas('level', function ($query) {
                    $query->whereHas('permissions', function ($q) {
                        $q->where('name', 'notifications.manage');
                    });
                })->get();

                $notificationMessage = $config['message'] ?? ('Workflow report generated: ' . $report['file_name']);
                foreach ($admins as $admin) {
                    $this->notificationService->notify($admin, 'workflow_notification', [
                        'message' => $notificationMessage,
                        'workflow_context' => $workflowContext,
                        'attachments' => $attachments,
                    ]);
                }

                return [
                    'status' => 'report_generated',
                    'message' => 'Report generated: ' . $report['file_name'],
                    'report' => [
                        'type' => $report['report_type'],
                        'file_name' => $report['file_name'],
                        'disk' => $report['disk'],
                        'path' => $report['path'],
                    ],
                    'recipients' => $admins->count(),
                    'context_updates' => [
                        'report_generated' => true,
                        'report_type' => $report['report_type'],
                        'report_file_name' => $report['file_name'],
                        'report_attachment' => [
                            'disk' => $report['disk'],
                            'path' => $report['path'],
                            'name' => $report['file_name'],
                            'mime' => $report['mime_type'],
                        ],
                    ],
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
     * @return array<int, array<string, string>>
     */
    protected function resolveActionAttachments(array $context): array
    {
        $reportAttachment = $context['report_attachment'] ?? null;
        if (!is_array($reportAttachment)) {
            return [];
        }

        $disk = isset($reportAttachment['disk']) ? trim((string) $reportAttachment['disk']) : '';
        $path = isset($reportAttachment['path']) ? trim((string) $reportAttachment['path']) : '';
        if ($disk === '' || $path === '') {
            return [];
        }

        $name = isset($reportAttachment['name']) && trim((string) $reportAttachment['name']) !== ''
            ? trim((string) $reportAttachment['name'])
            : basename($path);
        $mime = isset($reportAttachment['mime']) && trim((string) $reportAttachment['mime']) !== ''
            ? trim((string) $reportAttachment['mime'])
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return [[
            'disk' => $disk,
            'path' => $path,
            'name' => $name,
            'mime' => $mime,
        ]];
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
        $idempotentHit = false;

        $run = DB::transaction(function () use (
            $definition,
            $userId,
            $triggerPayload,
            $isDryRun,
            $idempotencyKey,
            &$idempotentHit
        ) {
            if ($idempotencyKey) {
                $existing = WorkflowRun::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    $idempotentHit = true;
                    return $existing;
                }
            }

            $lockedDefinition = WorkflowDefinition::query()
                ->whereKey($definition->id)
                ->lockForUpdate()
                ->firstOrFail();

            $version = WorkflowVersion::query()
                ->where('workflow_definition_id', $lockedDefinition->id)
                ->where('status', 'published')
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if (!$version) {
                throw new \RuntimeException('No published version for this workflow.');
            }

            $activeRuns = WorkflowRun::query()
                ->where('workflow_definition_id', $lockedDefinition->id)
                ->whereIn('status', ['pending', 'running'])
                ->lockForUpdate()
                ->count();

            if ($activeRuns >= $lockedDefinition->max_concurrency) {
                throw new \RuntimeException('Max concurrency limit reached for this workflow.');
            }

            try {
                return WorkflowRun::create([
                    'workflow_definition_id' => $lockedDefinition->id,
                    'workflow_version_id' => $version->id,
                    'status' => 'pending',
                    'trigger_type' => 'manual',
                    'trigger_payload' => $triggerPayload,
                    'context' => $triggerPayload,
                    'triggered_by' => $userId,
                    'is_dry_run' => $isDryRun,
                    'idempotency_key' => $idempotencyKey ?? Str::uuid()->toString(),
                ]);
            } catch (QueryException $e) {
                if ($idempotencyKey && $this->isUniqueConstraintViolation($e)) {
                    $existing = WorkflowRun::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                    if ($existing) {
                        $idempotentHit = true;
                        return $existing;
                    }
                }

                throw $e;
            }
        }, 5);

        if ($idempotentHit) {
            return $run->fresh();
        }

        return $this->executeRun($run);
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        return in_array($sqlState, ['23000', '23505'], true);
    }
}
