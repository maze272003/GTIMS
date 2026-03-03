<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Branch;
use App\Models\UserLevel;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Services\WorkflowEngineService;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowEngineService $engine;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(WorkflowEngineService::class);

        $level = UserLevel::create(['name' => 'superadmin', 'description' => 'Super Admin']);
        $this->user = User::factory()->create(['user_level_id' => $level->id]);
        Branch::factory()->create(['is_main' => true, 'is_archived' => false]);
    }

    protected function createWorkflowWithNodes(array $nodes, array $edges, string $status = 'published'): array
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'description' => 'A test workflow',
            'status' => $status === 'published' ? 'active' : 'draft',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);

        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => $status,
            'published_by' => $status === 'published' ? $this->user->id : null,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        foreach ($nodes as $node) {
            WorkflowNode::create(array_merge(['workflow_version_id' => $version->id], $node));
        }

        foreach ($edges as $edge) {
            WorkflowEdge::create(array_merge(['workflow_version_id' => $version->id], $edge));
        }

        return [$workflow, $version];
    }

    public function test_get_node_catalog_returns_all_types(): void
    {
        $catalog = $this->engine->getNodeCatalog();

        $this->assertArrayHasKey('triggers', $catalog);
        $this->assertArrayHasKey('conditions', $catalog);
        $this->assertArrayHasKey('actions', $catalog);
        $this->assertNotEmpty($catalog['triggers']);
        $this->assertNotEmpty($catalog['conditions']);
        $this->assertNotEmpty($catalog['actions']);
    }

    public function test_validate_graph_requires_trigger(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify'],
            ],
            [],
            'draft'
        );

        $version->load('nodes', 'edges');
        $errors = $this->engine->validateGraph($version);

        $this->assertNotEmpty($errors);
        $this->assertContains('Workflow must have at least one trigger node.', $errors);
    }

    public function test_validate_graph_requires_action(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
            ],
            [],
            'draft'
        );

        $version->load('nodes', 'edges');
        $errors = $this->engine->validateGraph($version);

        $this->assertNotEmpty($errors);
        $this->assertContains('Workflow must have at least one action node.', $errors);
    }

    public function test_validate_graph_detects_cycles(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify'],
                ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'create_hold', 'label' => 'Hold'],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
                ['source_node_id' => 'action_1', 'target_node_id' => 'action_2'],
                ['source_node_id' => 'action_2', 'target_node_id' => 'action_1'], // Cycle
            ],
            'draft'
        );

        $version->load('nodes', 'edges');
        $errors = $this->engine->validateGraph($version);

        $this->assertContains('Workflow graph contains a cycle. Only DAGs are allowed.', $errors);
    }

    public function test_validate_graph_valid_dag(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify'],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ],
            'draft'
        );

        $version->load('nodes', 'edges');
        $errors = $this->engine->validateGraph($version);

        $this->assertEmpty($errors);
    }

    public function test_start_run_executes_workflow(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Test notification']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id);

        $this->assertEquals('completed', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertEquals(2, $run->steps()->count());
    }

    public function test_start_run_dry_run_does_not_execute_actions(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Test']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id, [], true);

        $this->assertEquals('completed', $run->status);
        $this->assertTrue($run->is_dry_run);
    }

    public function test_start_run_respects_idempotency(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Test']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $key = 'test-idempotency-key';
        $run1 = $this->engine->startRun($workflow, $this->user->id, [], false, $key);
        $run2 = $this->engine->startRun($workflow, $this->user->id, [], false, $key);

        $this->assertEquals($run1->id, $run2->id);
    }

    public function test_start_run_fails_without_published_version(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify'],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ],
            'draft'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No published version for this workflow.');

        $this->engine->startRun($workflow, $this->user->id);
    }

    public function test_condition_node_evaluates_quantity_threshold(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'cond_1', 'type' => 'condition', 'action_type' => 'quantity_threshold', 'label' => 'Qty < 10', 'config' => ['operator' => '<', 'value' => 10]],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Low stock!']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'cond_1'],
                ['source_node_id' => 'cond_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id, ['quantity' => 5]);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals(3, $run->steps()->count());
    }

    public function test_workflow_definition_relationships(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify'],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $this->assertCount(1, $workflow->versions);
        $this->assertEquals($this->user->id, $workflow->creator->id);
        $published = $workflow->publishedVersion();
        $this->assertNotNull($published);
        $this->assertEquals(2, $published->nodes->count());
        $this->assertEquals(1, $published->edges->count());
    }

    public function test_max_concurrency_limit(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Test']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $workflow->update(['max_concurrency' => 1]);

        // Create a pending run to fill concurrency
        WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'running',
            'triggered_by' => $this->user->id,
            'idempotency_key' => 'existing-run',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max concurrency limit reached');

        $this->engine->startRun($workflow, $this->user->id);
    }

    public function test_generate_report_action_creates_excel_and_updates_context(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'daily_schedule', 'label' => 'Daily', 'config' => ['cron' => '0 8 * * *']],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'generate_report', 'label' => 'Generate', 'config' => ['report_type' => 'low_stock']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id);

        $this->assertEquals('completed', $run->status);
        $this->assertTrue((bool) data_get($run->context, 'report_generated'));
        $this->assertEquals('low_stock', data_get($run->context, 'report_type'));
        $this->assertNotEmpty(data_get($run->context, 'report_file_name'));
        $this->assertNotEmpty(data_get($run->context, 'report_attachment.path'));
        $this->assertEquals('local', data_get($run->context, 'report_attachment.disk'));

        $reportPath = (string) data_get($run->context, 'report_attachment.path');
        $this->assertTrue(Storage::disk('local')->exists($reportPath));

        Storage::disk('local')->delete($reportPath);
    }
}
