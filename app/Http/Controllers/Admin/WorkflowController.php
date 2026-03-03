<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdempotencyKey;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowVersion;
use App\Models\WorkflowRun;
use App\Models\WorkflowPermission;
use App\Services\WorkflowEngineService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowEngineService $engine,
        protected AuditService $auditService,
    ) {}

    /**
     * List all workflows.
     */
    public function index(Request $request)
    {
        $query = WorkflowDefinition::with('creator', 'versions')
            ->withCount('runs');
        $templates = $this->engine->getWorkflowTemplates();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $workflows = $query->latest()->paginate(15);

        if ($request->wantsJson()) {
            return response()->json([
                'workflows' => $workflows,
                'templates' => $templates,
            ]);
        }

        return view('admin.workflows.index', compact('workflows', 'templates'));
    }

    /**
     * Show the workflow editor page.
     */
    public function editor(WorkflowDefinition $workflow)
    {
        $workflow->load('creator');
        $catalog = $this->engine->getNodeCatalog();

        $latestVersion = $workflow->versions()
            ->with('nodes', 'edges')
            ->latest('version_number')
            ->first();

        $graph = $latestVersion?->graph_data
            ?: [
                'nodes' => $latestVersion?->nodes?->map(fn (WorkflowNode $node) => [
                    'node_id' => $node->node_id,
                    'type' => $node->type,
                    'action_type' => $node->action_type,
                    'label' => $node->label,
                    'config' => $node->config ?? [],
                    'position' => $node->position ?? ['x' => 100, 'y' => 100],
                ])->values()->all() ?? [],
                'edges' => $latestVersion?->edges?->map(fn (WorkflowEdge $edge) => [
                    'source_node_id' => $edge->source_node_id,
                    'target_node_id' => $edge->target_node_id,
                    'label' => $edge->label,
                    'condition_branch' => $edge->condition_branch,
                ])->values()->all() ?? [],
            ];

        $initialGraphHash = $latestVersion ? $this->engine->computeGraphHash($graph) : null;
        $initialSyncToken = $latestVersion ? $this->buildSyncToken($latestVersion, $initialGraphHash) : null;

        return view('admin.workflows.editor', compact('workflow', 'catalog', 'latestVersion', 'initialGraphHash', 'initialSyncToken'));
    }

    /**
     * Create a new workflow.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'template_key' => 'nullable|string|max:120',
        ]);

        $template = null;
        if (isset($validated['template_key']) && trim((string) $validated['template_key']) !== '') {
            $template = $this->engine->findWorkflowTemplate((string) $validated['template_key']);
            if (!$template) {
                throw ValidationException::withMessages([
                    'template_key' => ['Selected workflow template does not exist.'],
                ]);
            }
        }

        $workflow = DB::transaction(function () use ($validated, $template) {
            $workflow = WorkflowDefinition::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? ($template['description'] ?? null),
                'branch_id' => $validated['branch_id'] ?? null,
                'created_by' => Auth::id(),
                'status' => 'draft',
                'current_version' => 1,
            ]);

            // Create initial version
            $version = WorkflowVersion::create([
                'workflow_definition_id' => $workflow->id,
                'version_number' => 1,
                'status' => 'draft',
            ]);

            if ($template) {
                $templateGraph = is_array($template['graph'] ?? null) ? $template['graph'] : ['nodes' => [], 'edges' => []];
                $graphValidation = $this->engine->validateGraphPayload($templateGraph);
                if (!$graphValidation['valid']) {
                    throw ValidationException::withMessages([
                        'template_key' => $graphValidation['errors'],
                    ]);
                }

                $graph = $graphValidation['graph'];
                if (!empty($graph['nodes'])) {
                    $version->nodes()->createMany($graph['nodes']);
                }
                if (!empty($graph['edges'])) {
                    $version->edges()->createMany($graph['edges']);
                }

                $version->update([
                    'graph_data' => array_merge($graph, [
                        '_template' => [
                            'key' => $template['key'] ?? null,
                            'name' => $template['name'] ?? null,
                            'completion_criteria' => $template['completion_criteria'] ?? [],
                            'capabilities' => $template['capabilities'] ?? [],
                        ],
                    ]),
                    'change_summary' => 'Initialized from template: ' . ($template['name'] ?? ($template['key'] ?? 'unknown')),
                ]);
            }

            $this->auditService->record(
                'workflow_created', 'WorkflowDefinition', $workflow->id,
                Auth::id(), null, $workflow->toArray(), 'Workflow created',
                array_filter([
                    'template_key' => $template['key'] ?? null,
                    'template_name' => $template['name'] ?? null,
                ])
            );

            return $workflow;
        });

        if ($request->wantsJson()) {
            return response()->json($workflow, 201);
        }

        return redirect()->route('admin.workflows.editor', $workflow)
            ->with('success', 'Workflow created successfully.');
    }

    /**
     * Save workflow graph (nodes + edges) for the current draft version.
     */
    public function saveGraph(Request $request, WorkflowDefinition $workflow)
    {
        $idempotencyKey = $this->sanitizeIdempotencyKey($request->header('X-Idempotency-Key'));
        $idempotencyAction = "workflow.save-graph.{$workflow->id}";
        if ($idempotencyKey) {
            $existing = $this->findIdempotencyResponse($idempotencyKey, $idempotencyAction);
            if ($existing) {
                return response()->json($existing);
            }
        }

        $validated = $request->validate([
            'nodes' => 'required|array',
            'nodes.*.node_id' => 'required|string|max:100',
            'nodes.*.type' => ['required', Rule::in(['trigger', 'condition', 'action'])],
            'nodes.*.action_type' => 'required|string|max:100',
            'nodes.*.label' => 'required|string|max:255',
            'nodes.*.config' => 'nullable|array',
            'nodes.*.position' => 'nullable|array',
            'edges' => 'nullable|array',
            'edges.*.source_node_id' => 'required|string|max:100',
            'edges.*.target_node_id' => 'required|string|max:100',
            'edges.*.label' => 'nullable|string|max:255',
            'edges.*.condition_branch' => 'nullable|string|max:50',
        ]);

        $graphValidation = $this->engine->validateGraphPayload($validated);
        if (!$graphValidation['valid']) {
            return response()->json([
                'success' => false,
                'errors' => $graphValidation['errors'],
            ], 422);
        }

        $graph = $graphValidation['graph'];

        [$version, $graphHash] = DB::transaction(function () use ($workflow, $graph) {
            $lockedWorkflow = WorkflowDefinition::query()
                ->whereKey($workflow->id)
                ->lockForUpdate()
                ->firstOrFail();

            $version = WorkflowVersion::query()
                ->where('workflow_definition_id', $lockedWorkflow->id)
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if (!$version || $version->status === 'published') {
                // Create new draft version
                $newVersionNumber = ($version ? $version->version_number : 0) + 1;
                $version = WorkflowVersion::create([
                    'workflow_definition_id' => $lockedWorkflow->id,
                    'version_number' => $newVersionNumber,
                    'status' => 'draft',
                ]);
                $lockedWorkflow->update([
                    'current_version' => $newVersionNumber,
                    'updated_by' => Auth::id(),
                ]);
            } elseif ((int) $lockedWorkflow->current_version !== (int) $version->version_number) {
                $lockedWorkflow->update([
                    'current_version' => $version->version_number,
                    'updated_by' => Auth::id(),
                ]);
            }

            $graphHash = $this->engine->computeGraphHash($graph);
            $existingGraph = is_array($version->graph_data) ? $version->graph_data : ['nodes' => [], 'edges' => []];
            $existingGraphHash = $this->engine->computeGraphHash($existingGraph);

            if ($graphHash !== $existingGraphHash) {
                // Replace all nodes and edges atomically inside transaction.
                $version->nodes()->delete();
                $version->edges()->delete();

                $version->nodes()->createMany($graph['nodes']);
                if (!empty($graph['edges'])) {
                    $version->edges()->createMany($graph['edges']);
                }

                // Store full graph data as JSON snapshot.
                $version->update(['graph_data' => $graph]);
            } else {
                $version->touch();
            }

            $lockedWorkflow->update(['updated_by' => Auth::id()]);

            return [$version->fresh(['nodes', 'edges']), $graphHash];
        }, 5);

        $response = [
            'success' => true,
            'graph_hash' => $graphHash,
            'sync_token' => $this->buildSyncToken($version, $graphHash),
            'version' => $version->toArray(),
        ];

        if ($idempotencyKey) {
            $this->storeIdempotencyResponse($idempotencyKey, $idempotencyAction, $response);
        }

        return response()->json($response);
    }

    /**
     * Validate a workflow graph server-side.
     */
    public function validate(Request $request, WorkflowDefinition $workflow)
    {
        $version = $workflow->versions()->latest('version_number')->first();

        if (!$version) {
            return response()->json(['errors' => ['No version found.']], 422);
        }

        $version->load('nodes', 'edges');
        $errors = $this->engine->validateGraph($version);

        if (!empty($errors)) {
            return response()->json(['valid' => false, 'errors' => $errors], 422);
        }

        return response()->json(['valid' => true, 'errors' => []]);
    }

    /**
     * Publish a workflow version.
     */
    public function publish(WorkflowDefinition $workflow)
    {
        $version = $workflow->versions()
            ->with('nodes', 'edges')
            ->latest('version_number')
            ->first();

        if (!$version) {
            return response()->json(['error' => 'No version to publish.'], 422);
        }

        $errors = $this->engine->validateGraph($version);

        if (!empty($errors)) {
            return response()->json(['valid' => false, 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($workflow, $version) {
            $lockedWorkflow = WorkflowDefinition::query()
                ->whereKey($workflow->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVersion = WorkflowVersion::query()
                ->where('workflow_definition_id', $lockedWorkflow->id)
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Archive any previously published version
            $lockedWorkflow->versions()
                ->where('status', 'published')
                ->where('id', '!=', $lockedVersion->id)
                ->update(['status' => 'archived']);

            $lockedVersion->update([
                'status' => 'published',
                'published_by' => Auth::id(),
                'published_at' => now(),
            ]);

            $lockedWorkflow->update([
                'status' => 'active',
                'current_version' => $lockedVersion->version_number,
                'updated_by' => Auth::id(),
            ]);

            $this->auditService->record(
                'workflow_published', 'WorkflowDefinition', $lockedWorkflow->id,
                Auth::id(), null,
                ['version' => $lockedVersion->version_number],
                'Workflow published'
            );
        }, 5);

        return response()->json(['success' => true, 'message' => 'Workflow published.']);
    }

    /**
     * Disable a workflow.
     */
    public function disable(WorkflowDefinition $workflow)
    {
        $workflow->update(['status' => 'disabled', 'updated_by' => Auth::id()]);

        $this->auditService->record(
            'workflow_disabled', 'WorkflowDefinition', $workflow->id,
            Auth::id(), ['status' => 'active'], ['status' => 'disabled'], 'Workflow disabled'
        );

        return response()->json(['success' => true, 'message' => 'Workflow disabled.']);
    }

    /**
     * Run a workflow manually.
     */
    public function run(Request $request, WorkflowDefinition $workflow)
    {
        $validated = $request->validate([
            'trigger_payload' => 'nullable|array',
            'dry_run' => 'nullable|boolean',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $idempotencyKey = $this->sanitizeIdempotencyKey(
                $request->header('X-Idempotency-Key') ?? ($validated['idempotency_key'] ?? null)
            );

            $run = $this->engine->startRun(
                $workflow,
                Auth::id(),
                $validated['trigger_payload'] ?? [],
                $validated['dry_run'] ?? false,
                $idempotencyKey
            );

            return response()->json([
                'success' => true,
                'run' => $run->load('steps'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * List workflow runs.
     */
    public function runs(Request $request, WorkflowDefinition $workflow)
    {
        $runs = $workflow->runs()
            ->with('triggeredBy', 'steps')
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($runs);
        }

        return view('admin.workflows.runs', compact('workflow', 'runs'));
    }

    /**
     * Show a single run with step details.
     */
    public function showRun(WorkflowDefinition $workflow, WorkflowRun $run)
    {
        $run->load('steps', 'triggeredBy', 'version');

        return view('admin.workflows.run-detail', compact('workflow', 'run'));
    }

    /**
     * Get the node catalog (triggers, conditions, actions).
     */
    public function catalog()
    {
        $catalog = $this->engine->getNodeCatalog();
        $catalog['templates'] = $this->engine->getWorkflowTemplates();

        return response()->json($catalog);
    }

    /**
     * List workflow template library.
     */
    public function templates()
    {
        return response()->json([
            'templates' => $this->engine->getWorkflowTemplates(),
        ]);
    }

    /**
     * Get latest graph state for polling-based real-time synchronization.
     */
    public function graphState(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        $version = $workflow->versions()
            ->with('nodes', 'edges')
            ->latest('version_number')
            ->first();

        if (!$version) {
            return response()->json([
                'changed' => false,
                'version' => null,
                'graph_hash' => null,
                'sync_token' => null,
            ]);
        }

        $graph = $version->graph_data ?: [
            'nodes' => $version->nodes->map(fn (WorkflowNode $node) => [
                'node_id' => $node->node_id,
                'type' => $node->type,
                'action_type' => $node->action_type,
                'label' => $node->label,
                'config' => $node->config ?? [],
                'position' => $node->position ?? ['x' => 100, 'y' => 100],
            ])->values()->all(),
            'edges' => $version->edges->map(fn (WorkflowEdge $edge) => [
                'source_node_id' => $edge->source_node_id,
                'target_node_id' => $edge->target_node_id,
                'label' => $edge->label,
                'condition_branch' => $edge->condition_branch,
            ])->values()->all(),
        ];

        $graphHash = $this->engine->computeGraphHash($graph);
        $syncToken = $this->buildSyncToken($version, $graphHash);
        $since = (string) $request->query('since', '');

        if ($since !== '' && hash_equals($syncToken, $since)) {
            return response()->json([
                'changed' => false,
                'graph_hash' => $graphHash,
                'sync_token' => $syncToken,
            ]);
        }

        return response()->json([
            'changed' => true,
            'graph_hash' => $graphHash,
            'sync_token' => $syncToken,
            'version' => $version,
        ]);
    }

    /**
     * Delete a workflow (soft delete).
     */
    public function destroy(WorkflowDefinition $workflow)
    {
        $workflow->update(['status' => 'disabled', 'updated_by' => Auth::id()]);
        $workflow->delete();

        $this->auditService->record(
            'workflow_deleted', 'WorkflowDefinition', $workflow->id,
            Auth::id(), $workflow->toArray(), null, 'Workflow deleted'
        );

        return response()->json(['success' => true, 'message' => 'Workflow deleted.']);
    }

    /**
     * List permissions for a workflow.
     */
    public function permissions(WorkflowDefinition $workflow)
    {
        $permissions = $workflow->permissions()->with('user:id,name,email')->get();

        return response()->json(['permissions' => $permissions]);
    }

    /**
     * Add a permission to a workflow.
     */
    public function addPermission(Request $request, WorkflowDefinition $workflow)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'permission' => ['required', Rule::in(['view', 'edit', 'publish', 'run'])],
        ]);

        $permission = WorkflowPermission::firstOrCreate([
            'workflow_definition_id' => $workflow->id,
            'user_id' => $validated['user_id'],
            'permission' => $validated['permission'],
        ]);

        $this->auditService->record(
            'workflow_permission_added', 'WorkflowPermission', $permission->id,
            Auth::id(), null,
            ['user_id' => $validated['user_id'], 'permission' => $validated['permission']],
            'Workflow permission granted'
        );

        return response()->json(['success' => true, 'permission' => $permission->load('user:id,name,email')]);
    }

    /**
     * Remove a permission from a workflow.
     */
    public function removePermission(WorkflowDefinition $workflow, WorkflowPermission $permission)
    {
        if ($permission->workflow_definition_id !== $workflow->id) {
            return response()->json(['error' => 'Permission does not belong to this workflow.'], 403);
        }

        $this->auditService->record(
            'workflow_permission_removed', 'WorkflowPermission', $permission->id,
            Auth::id(), $permission->toArray(), null,
            'Workflow permission revoked'
        );

        $permission->delete();

        return response()->json(['success' => true, 'message' => 'Permission removed.']);
    }

    protected function sanitizeIdempotencyKey(?string $key): ?string
    {
        if (!is_string($key)) {
            return null;
        }

        $trimmed = trim($key);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 255);
    }

    protected function findIdempotencyResponse(string $key, string $action): ?array
    {
        $existing = IdempotencyKey::query()->where('key', $key)->first();
        if (!$existing) {
            return null;
        }

        if ((int) $existing->user_id !== (int) Auth::id() || $existing->action !== $action) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Idempotency key already used for a different request context.'],
            ]);
        }

        return is_array($existing->response) ? $existing->response : null;
    }

    protected function storeIdempotencyResponse(string $key, string $action, array $response): void
    {
        IdempotencyKey::query()->updateOrCreate(
            ['key' => $key],
            [
                'user_id' => Auth::id(),
                'action' => $action,
                'response' => $response,
            ]
        );
    }

    protected function buildSyncToken(WorkflowVersion $version, ?string $graphHash = null): string
    {
        $hash = $graphHash;
        if (!$hash) {
            $graph = is_array($version->graph_data) ? $version->graph_data : ['nodes' => [], 'edges' => []];
            $hash = $this->engine->computeGraphHash($graph);
        }

        $seed = implode('|', [
            $version->id,
            $version->version_number,
            optional($version->updated_at)->toIso8601String(),
            $hash,
        ]);

        return hash('sha256', $seed);
    }
}
