<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowRun;
use App\Models\WorkflowPermission;
use App\Services\WorkflowEngineService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            return response()->json($workflows);
        }

        return view('admin.workflows.index', compact('workflows'));
    }

    /**
     * Show the workflow editor page.
     */
    public function editor(WorkflowDefinition $workflow)
    {
        $workflow->load('versions.nodes', 'versions.edges', 'creator');
        $catalog = $this->engine->getNodeCatalog();

        $latestVersion = $workflow->versions()->latest('version_number')->first();

        return view('admin.workflows.editor', compact('workflow', 'catalog', 'latestVersion'));
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
        ]);

        $workflow = DB::transaction(function () use ($validated) {
            $workflow = WorkflowDefinition::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'created_by' => Auth::id(),
                'status' => 'draft',
                'current_version' => 1,
            ]);

            // Create initial version
            WorkflowVersion::create([
                'workflow_definition_id' => $workflow->id,
                'version_number' => 1,
                'status' => 'draft',
            ]);

            $this->auditService->record(
                'workflow_created', 'WorkflowDefinition', $workflow->id,
                Auth::id(), null, $workflow->toArray(), 'Workflow created'
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

        $version = DB::transaction(function () use ($workflow, $validated) {
            $version = $workflow->versions()->latest('version_number')->first();

            if (!$version || $version->status === 'published') {
                // Create new draft version
                $newVersionNumber = ($version ? $version->version_number : 0) + 1;
                $version = WorkflowVersion::create([
                    'workflow_definition_id' => $workflow->id,
                    'version_number' => $newVersionNumber,
                    'status' => 'draft',
                ]);
                $workflow->update(['current_version' => $newVersionNumber, 'updated_by' => Auth::id()]);
            }

            // Replace all nodes and edges
            $version->nodes()->delete();
            $version->edges()->delete();

            foreach ($validated['nodes'] as $nodeData) {
                $version->nodes()->create($nodeData);
            }

            foreach ($validated['edges'] ?? [] as $edgeData) {
                $version->edges()->create($edgeData);
            }

            // Store full graph data as JSON snapshot
            $version->update(['graph_data' => $validated]);

            return $version;
        });

        return response()->json([
            'success' => true,
            'version' => $version->load('nodes', 'edges'),
        ]);
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
        $version = $workflow->versions()->latest('version_number')->first();

        if (!$version) {
            return response()->json(['error' => 'No version to publish.'], 422);
        }

        $version->load('nodes', 'edges');
        $errors = $this->engine->validateGraph($version);

        if (!empty($errors)) {
            return response()->json(['valid' => false, 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($workflow, $version) {
            // Archive any previously published version
            $workflow->versions()
                ->where('status', 'published')
                ->where('id', '!=', $version->id)
                ->update(['status' => 'archived']);

            $version->update([
                'status' => 'published',
                'published_by' => Auth::id(),
                'published_at' => now(),
            ]);

            $workflow->update(['status' => 'active', 'updated_by' => Auth::id()]);

            $this->auditService->record(
                'workflow_published', 'WorkflowDefinition', $workflow->id,
                Auth::id(), null,
                ['version' => $version->version_number],
                'Workflow published'
            );
        });

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
        ]);

        try {
            $run = $this->engine->startRun(
                $workflow,
                Auth::id(),
                $validated['trigger_payload'] ?? [],
                $validated['dry_run'] ?? false
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
        return response()->json($this->engine->getNodeCatalog());
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
}
