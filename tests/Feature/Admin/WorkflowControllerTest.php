<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected UserLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        $this->level = UserLevel::create(['name' => 'superadmin']);
        $branch = Branch::factory()->create([
            'name' => 'RHU 1',
            'code' => 'rhu-1',
            'is_archived' => false,
        ]);

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'user_level_id' => $this->level->id,
            'branch_id' => $branch->id,
        ]);

        // Grant workflow permissions
        foreach (['workflows.view', 'workflows.create', 'workflows.edit', 'workflows.publish', 'workflows.run', 'workflows.delete', 'dashboard.view'] as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm]);
            RolePermission::firstOrCreate([
                'user_level_id' => $this->level->id,
                'permission_id' => $p->id,
            ]);
        }
    }

    public function test_index_page_loads(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.workflows.index'));
        $response->assertStatus(200);
        $response->assertSee('Automation Builder');
    }

    public function test_store_creates_workflow(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.workflows.store'), [
            'name' => 'Test Workflow',
            'description' => 'A test workflow',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workflow_definitions', ['name' => 'Test Workflow']);
        $this->assertDatabaseHas('workflow_versions', ['version_number' => 1, 'status' => 'draft']);
    }

    public function test_editor_page_loads(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);
        WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.workflows.editor', $workflow));
        $response->assertStatus(200);
        $response->assertSee('Test Workflow');
    }

    public function test_save_graph_stores_nodes_and_edges(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);
        WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.workflows.save-graph', $workflow), [
            'nodes' => [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock', 'config' => [], 'position' => ['x' => 100, 'y' => 50]],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Alert!'], 'position' => ['x' => 100, 'y' => 200]],
            ],
            'edges' => [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('workflow_nodes', ['node_id' => 'trigger_1', 'action_type' => 'low_stock_reached']);
        $this->assertDatabaseHas('workflow_nodes', ['node_id' => 'action_1', 'action_type' => 'notify']);
        $this->assertDatabaseHas('workflow_edges', ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1']);
    }

    public function test_save_graph_rejects_unknown_config_field(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);
        WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.workflows.save-graph', $workflow), [
            'nodes' => [
                [
                    'node_id' => 'trigger_1',
                    'type' => 'trigger',
                    'action_type' => 'low_stock_reached',
                    'label' => 'Low Stock',
                    'config' => ['unexpected' => 'value'],
                    'position' => ['x' => 100, 'y' => 50],
                ],
                [
                    'node_id' => 'action_1',
                    'type' => 'action',
                    'action_type' => 'notify',
                    'label' => 'Notify',
                    'config' => ['message' => 'Alert!'],
                    'position' => ['x' => 100, 'y' => 200],
                ],
            ],
            'edges' => [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['errors']);
    }

    public function test_graph_state_endpoint_returns_sync_payload(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        WorkflowNode::create([
            'workflow_version_id' => $version->id,
            'node_id' => 'trigger_1',
            'type' => 'trigger',
            'action_type' => 'low_stock_reached',
            'label' => 'Low Stock',
            'config' => ['threshold' => 10],
            'position' => ['x' => 100, 'y' => 100],
        ]);
        WorkflowNode::create([
            'workflow_version_id' => $version->id,
            'node_id' => 'action_1',
            'type' => 'action',
            'action_type' => 'notify',
            'label' => 'Notify',
            'config' => ['message' => 'Alert!'],
            'position' => ['x' => 100, 'y' => 220],
        ]);
        WorkflowEdge::create([
            'workflow_version_id' => $version->id,
            'source_node_id' => 'trigger_1',
            'target_node_id' => 'action_1',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('admin.workflows.graph-state', $workflow));
        $response->assertOk();
        $response->assertJsonPath('changed', true);
        $response->assertJsonStructure([
            'changed',
            'graph_hash',
            'sync_token',
            'version' => ['id', 'nodes', 'edges'],
        ]);
    }

    public function test_save_graph_is_idempotent_with_header_key(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);
        WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $payload = [
            'nodes' => [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock', 'config' => [], 'position' => ['x' => 100, 'y' => 50]],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Alert!'], 'position' => ['x' => 100, 'y' => 200]],
            ],
            'edges' => [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ],
        ];

        $headers = ['X-Idempotency-Key' => 'workflow-save-key-1'];

        $first = $this->actingAs($this->user)->postJson(route('admin.workflows.save-graph', $workflow), $payload, $headers);
        $second = $this->actingAs($this->user)->postJson(route('admin.workflows.save-graph', $workflow), $payload, $headers);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('sync_token'), $second->json('sync_token'));
        $this->assertEquals(2, WorkflowNode::query()->count());
        $this->assertEquals(1, WorkflowEdge::query()->count());
    }

    public function test_validate_endpoint_detects_invalid_graph(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        // Add only an action node (no trigger)
        WorkflowNode::create([
            'workflow_version_id' => $version->id,
            'node_id' => 'action_1',
            'type' => 'action',
            'action_type' => 'notify',
            'label' => 'Notify',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.workflows.validate', $workflow));
        $response->assertStatus(422);
        $response->assertJsonPath('valid', false);
    }

    public function test_publish_workflow(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        WorkflowNode::create(['workflow_version_id' => $version->id, 'node_id' => 't1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock']);
        WorkflowNode::create(['workflow_version_id' => $version->id, 'node_id' => 'a1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify']);
        WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => 't1', 'target_node_id' => 'a1']);

        $response = $this->actingAs($this->user)->postJson(route('admin.workflows.publish', $workflow));
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('workflow_versions', ['id' => $version->id, 'status' => 'published']);
        $this->assertDatabaseHas('workflow_definitions', ['id' => $workflow->id, 'status' => 'active']);
    }

    public function test_run_workflow(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'created_by' => $this->user->id,
            'status' => 'active',
            'current_version' => 1,
        ]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'published',
            'published_by' => $this->user->id,
            'published_at' => now(),
        ]);
        WorkflowNode::create(['workflow_version_id' => $version->id, 'node_id' => 't1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock']);
        WorkflowNode::create(['workflow_version_id' => $version->id, 'node_id' => 'a1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Test']]);
        WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => 't1', 'target_node_id' => 'a1']);

        $response = $this->actingAs($this->user)->postJson(route('admin.workflows.run', $workflow), [
            'dry_run' => true,
            'trigger_payload' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('run.status', 'completed');
    }

    public function test_catalog_returns_node_types(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('admin.workflows.catalog'));
        $response->assertOk();
        $response->assertJsonStructure(['triggers', 'conditions', 'actions']);
    }

    public function test_runs_page_loads(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.workflows.runs', $workflow));
        $response->assertStatus(200);
    }

    public function test_list_permissions(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 0,
        ]);

        WorkflowPermission::create([
            'workflow_definition_id' => $workflow->id,
            'user_id' => $this->user->id,
            'permission' => 'view',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('admin.workflows.permissions', $workflow));
        $response->assertOk();
        $response->assertJsonCount(1, 'permissions');
        $response->assertJsonPath('permissions.0.permission', 'view');
    }

    public function test_add_permission(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 0,
        ]);

        $otherUser = User::factory()->create([
            'user_level_id' => $this->level->id,
            'branch_id' => $this->user->branch_id,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.workflows.permissions.add', $workflow), [
            'user_id' => $otherUser->id,
            'permission' => 'edit',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('workflow_permissions', [
            'workflow_definition_id' => $workflow->id,
            'user_id' => $otherUser->id,
            'permission' => 'edit',
        ]);
    }

    public function test_add_permission_is_idempotent(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 0,
        ]);

        $data = [
            'user_id' => $this->user->id,
            'permission' => 'run',
        ];

        $this->actingAs($this->user)->postJson(route('admin.workflows.permissions.add', $workflow), $data);
        $this->actingAs($this->user)->postJson(route('admin.workflows.permissions.add', $workflow), $data);

        $this->assertEquals(1, WorkflowPermission::where([
            'workflow_definition_id' => $workflow->id,
            'user_id' => $this->user->id,
            'permission' => 'run',
        ])->count());
    }

    public function test_remove_permission(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 0,
        ]);

        $permission = WorkflowPermission::create([
            'workflow_definition_id' => $workflow->id,
            'user_id' => $this->user->id,
            'permission' => 'view',
        ]);

        $response = $this->actingAs($this->user)->deleteJson(route('admin.workflows.permissions.remove', [$workflow, $permission]));
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('workflow_permissions', ['id' => $permission->id]);
    }

    public function test_add_permission_validates_input(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 0,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('admin.workflows.permissions.add', $workflow), [
            'user_id' => 999999,
            'permission' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_permissions_cascade_on_user_delete(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 0,
        ]);

        $otherUser = User::factory()->create([
            'user_level_id' => $this->level->id,
            'branch_id' => $this->user->branch_id,
        ]);

        WorkflowPermission::create([
            'workflow_definition_id' => $workflow->id,
            'user_id' => $otherUser->id,
            'permission' => 'view',
        ]);

        $this->assertDatabaseHas('workflow_permissions', ['user_id' => $otherUser->id]);

        $otherUser->forceDelete();

        $this->assertDatabaseMissing('workflow_permissions', ['user_id' => $otherUser->id]);
    }

    public function test_permissions_cascade_on_workflow_delete(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test',
            'created_by' => $this->user->id,
            'current_version' => 0,
        ]);

        WorkflowPermission::create([
            'workflow_definition_id' => $workflow->id,
            'user_id' => $this->user->id,
            'permission' => 'edit',
        ]);

        $this->assertDatabaseHas('workflow_permissions', ['workflow_definition_id' => $workflow->id]);

        $workflow->forceDelete();

        $this->assertDatabaseMissing('workflow_permissions', ['workflow_definition_id' => $workflow->id]);
    }
}
