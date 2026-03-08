<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Branch;
use App\Models\UserLevel;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function test_auto_allocate_order_uses_quantity_requested_from_source_branch(): void
    {
        $sourceBranch = Branch::factory()->create(['is_archived' => false]);
        $requestingBranch = Branch::factory()->create(['is_archived' => false]);
        $product = Product::factory()->create();

        $sourceBatch1 = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'SRC-B1',
            'quantity' => 6,
            'onhand_qty' => 6,
            'hold_qty' => 0,
            'expiry_date' => now()->addDays(10)->toDateString(),
            'is_archived' => false,
        ]);
        $sourceBatch2 = Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $sourceBranch->id,
            'batch_number' => 'SRC-B2',
            'quantity' => 6,
            'onhand_qty' => 6,
            'hold_qty' => 0,
            'expiry_date' => now()->addDays(20)->toDateString(),
            'is_archived' => false,
        ]);

        // This batch should not be used because allocation must follow source_branch_id.
        Inventory::create([
            'product_id' => $product->id,
            'branch_id' => $requestingBranch->id,
            'batch_number' => 'REQ-B1',
            'quantity' => 50,
            'onhand_qty' => 50,
            'hold_qty' => 0,
            'expiry_date' => now()->addDays(5)->toDateString(),
            'is_archived' => false,
        ]);

        $order = Order::create([
            'branch_id' => $requestingBranch->id,
            'user_id' => $this->user->id,
            'status' => 'approved',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 10,
            'source_branch_id' => $sourceBranch->id,
            'source_inventory_id' => $sourceBatch1->id,
            'source_batch_number' => $sourceBatch1->batch_number,
        ]);

        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'order_approved', 'label' => 'Order Approved'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'auto_allocate_order', 'label' => 'Allocate'],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id, ['order_id' => $order->id]);
        $this->assertEquals('completed', $run->status);

        $allocation = data_get($run->context, 'allocations.0');
        $this->assertSame(10, data_get($allocation, 'requested'));
        $this->assertSame(10, data_get($allocation, 'allocated'));
        $this->assertSame(0, data_get($allocation, 'shortfall'));
        $this->assertSame($sourceBranch->id, data_get($allocation, 'source_branch_id'));

        $batchInventoryIds = array_map(
            fn (array $batch) => (int) ($batch['inventory_id'] ?? 0),
            data_get($allocation, 'batches', [])
        );
        $this->assertContains($sourceBatch1->id, $batchInventoryIds);
        $this->assertContains($sourceBatch2->id, $batchInventoryIds);
    }

    public function test_condition_branch_execution_skips_inactive_branch_nodes(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'condition_1', 'type' => 'condition', 'action_type' => 'quantity_threshold', 'label' => 'Quantity < 10', 'config' => ['operator' => '<', 'value' => 10]],
                ['node_id' => 'action_true', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify True', 'config' => ['message' => 'True path']],
                ['node_id' => 'action_false', 'type' => 'action', 'action_type' => 'create_hold', 'label' => 'False Path Hold', 'config' => ['reason' => 'False path']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'condition_1'],
                ['source_node_id' => 'condition_1', 'target_node_id' => 'action_true', 'condition_branch' => 'true'],
                ['source_node_id' => 'condition_1', 'target_node_id' => 'action_false', 'condition_branch' => 'false'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id, ['quantity' => 5]);
        $this->assertEquals('completed', $run->status);

        $steps = $run->steps()->get()->keyBy('node_id');
        $this->assertEquals('completed', $steps->get('action_true')?->status);
        $this->assertEquals('skipped', $steps->get('action_false')?->status);
    }

    public function test_completion_gate_fails_when_required_notifications_are_missing(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'daily_schedule', 'label' => 'Daily', 'config' => ['cron' => '0 8 * * *']],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'completion_gate', 'label' => 'Gate', 'config' => ['require_notifications' => 1, 'require_error_resolution' => 1]],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id);
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Completion gate failed', (string) $run->error_message);
    }

    public function test_workflow_template_library_contains_required_business_templates(): void
    {
        $templates = $this->engine->getWorkflowTemplates();
        $keys = array_map(fn (array $template) => $template['key'] ?? null, $templates);

        $this->assertContains('employee_onboarding_automation', $keys);
        $this->assertContains('document_approval_hierarchy', $keys);
        $this->assertContains('cross_platform_data_sync', $keys);
        $this->assertContains('it_service_request_management', $keys);
        $this->assertContains('compliance_monitoring_control_loop', $keys);
        $this->assertContains('low_stock_alert_reorder', $keys);
        $this->assertContains('expiry_hold_and_report', $keys);
        $this->assertContains('order_approved_fefo_allocation', $keys);
        $this->assertContains('daily_stock_movement_report', $keys);
    }

    public function test_all_workflow_templates_pass_graph_validation(): void
    {
        $templates = $this->engine->getWorkflowTemplates();

        foreach ($templates as $template) {
            $validation = $this->engine->validateGraphPayload($template['graph']);
            $this->assertEmpty(
                $validation['errors'],
                "Template '{$template['key']}' failed validation: " . implode('; ', $validation['errors'])
            );
            $this->assertTrue(
                (bool) ($validation['valid'] ?? false),
                "Template '{$template['key']}' should be marked as valid."
            );
        }
    }

    public function test_notify_action_supports_specific_user_recipient_strategy(): void
    {
        $targetA = User::factory()->create(['user_level_id' => $this->user->user_level_id]);
        $targetB = User::factory()->create(['user_level_id' => $this->user->user_level_id]);

        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'order_created', 'label' => 'Order Created'],
                [
                    'node_id' => 'action_1',
                    'type' => 'action',
                    'action_type' => 'notify',
                    'label' => 'Notify Selected',
                    'config' => [
                        'message' => 'Targeted notification',
                        'recipient_strategy' => 'specific_users',
                        'recipient_user_ids' => [$targetA->id, $targetB->id],
                    ],
                ],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id);
        $this->assertEquals('completed', $run->status);

        $notifyStep = $run->steps()->where('node_id', 'action_1')->firstOrFail();
        $this->assertEquals(2, (int) data_get($notifyStep->output_snapshot, 'recipients', 0));
    }

    public function test_notify_action_supports_criteria_based_recipient_filters(): void
    {
        $branchA = Branch::factory()->create(['is_archived' => false]);
        $branchB = Branch::factory()->create(['is_archived' => false]);

        $eligibleLevel = UserLevel::create(['name' => 'eligible-level']);
        $ineligibleLevel = UserLevel::create(['name' => 'ineligible-level']);
        $permission = Permission::create(['name' => 'workflows.run']);
        RolePermission::create([
            'user_level_id' => $eligibleLevel->id,
            'permission_id' => $permission->id,
        ]);

        $eligibleUser = User::factory()->create([
            'user_level_id' => $eligibleLevel->id,
            'branch_id' => $branchA->id,
        ]);
        User::factory()->create([
            'user_level_id' => $eligibleLevel->id,
            'branch_id' => $branchB->id,
        ]);
        User::factory()->create([
            'user_level_id' => $ineligibleLevel->id,
            'branch_id' => $branchA->id,
        ]);

        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'data_sync_requested', 'label' => 'Sync Requested'],
                [
                    'node_id' => 'action_1',
                    'type' => 'action',
                    'action_type' => 'notify',
                    'label' => 'Notify Filtered',
                    'config' => [
                        'message' => 'Criteria-based notification',
                        'recipient_strategy' => 'criteria',
                        'recipient_branch_ids' => [$branchA->id],
                        'recipient_permissions' => ['workflows.run'],
                    ],
                ],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id, ['branch_id' => $branchA->id]);
        $this->assertEquals('completed', $run->status);

        $notifyStep = $run->steps()->where('node_id', 'action_1')->firstOrFail();
        $this->assertEquals(1, (int) data_get($notifyStep->output_snapshot, 'recipients', 0));
        $this->assertTrue((bool) data_get($run->context, 'confirmation_notifications_sent'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($run->context, 'confirmation_notification_count'));
        $this->assertNotNull($eligibleUser->id);
    }

    public function test_create_google_doc_action_stores_output_data_in_context(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'employee_onboarding_started', 'label' => 'Onboarding'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'create_google_doc', 'label' => 'Create Doc', 'config' => ['title' => 'Onboarding Packet']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $run = $this->engine->startRun($workflow, $this->user->id);
        $this->assertEquals('completed', $run->status);
        $this->assertTrue((bool) data_get($run->context, 'google_doc_created'));
        $this->assertStringContainsString('docs.google.com/document/d/', (string) data_get($run->context, 'google_doc_url'));

        $outputs = data_get($run->context, '_workflow_outputs', []);
        $this->assertIsArray($outputs);
        $this->assertNotEmpty($outputs);
        $this->assertContains('create_google_doc', array_map(fn ($item) => $item['action_type'] ?? null, $outputs));
    }

    // ─────────────────────────────────────────────────────────
    //  RETRY / DEAD-LETTER TESTS
    // ─────────────────────────────────────────────────────────

    public function test_handle_failed_run_schedules_retry(): void
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

        $run = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'retry_attempt' => 0,
            'max_retries' => 3,
            'idempotency_key' => 'retry-test-1',
        ]);

        $this->engine->handleFailedRun($run, new \RuntimeException('Test failure'));

        $run->refresh();
        $this->assertNotNull($run->next_retry_at);
        $this->assertFalse((bool) $run->is_dead_letter);
    }

    public function test_handle_failed_run_dead_letters_after_max_retries(): void
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

        $run = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'retry_attempt' => 3,
            'max_retries' => 3,
            'idempotency_key' => 'dead-letter-test-1',
        ]);

        $this->engine->handleFailedRun($run, new \RuntimeException('Final failure'));

        $run->refresh();
        $this->assertTrue((bool) $run->is_dead_letter);
        $this->assertNull($run->next_retry_at);
        $this->assertStringContainsString('Final failure', $run->error_message);
    }

    public function test_handle_failed_run_skips_dry_runs(): void
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

        $run = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'is_dry_run' => true,
            'retry_attempt' => 0,
            'max_retries' => 3,
            'idempotency_key' => 'dry-run-fail-test',
        ]);

        $this->engine->handleFailedRun($run, new \RuntimeException('Dry run fail'));

        $run->refresh();
        $this->assertFalse((bool) $run->is_dead_letter);
        $this->assertNull($run->next_retry_at);
    }

    public function test_rerun_from_dead_letter_creates_new_run(): void
    {
        [$workflow, $version] = $this->createWorkflowWithNodes(
            [
                ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock'],
                ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify', 'config' => ['message' => 'Rerun test']],
            ],
            [
                ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ]
        );

        $failedRun = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'trigger_type' => 'event',
            'trigger_payload' => ['quantity' => 5],
            'is_dead_letter' => true,
            'retry_attempt' => 3,
            'max_retries' => 3,
            'idempotency_key' => 'dead-letter-rerun-test-1',
            'error_message' => 'Previous failure',
        ]);

        $newRun = $this->engine->rerunFromDeadLetter($failedRun, $this->user->id);

        $this->assertNotEquals($failedRun->id, $newRun->id);
        $this->assertEquals($failedRun->id, $newRun->parent_run_id);
        $this->assertEquals(0, $newRun->retry_attempt);
        $this->assertEquals($failedRun->workflow_definition_id, $newRun->workflow_definition_id);
        $this->assertEquals($failedRun->workflow_version_id, $newRun->workflow_version_id);
        $this->assertNotEquals($failedRun->idempotency_key, $newRun->idempotency_key);
    }

    // ─────────────────────────────────────────────────────────
    //  WORKFLOW RUN SCOPES
    // ─────────────────────────────────────────────────────────

    public function test_dead_letter_scope(): void
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

        WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'is_dead_letter' => true,
            'idempotency_key' => 'dl-scope-1',
        ]);

        WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'completed',
            'triggered_by' => $this->user->id,
            'idempotency_key' => 'dl-scope-2',
        ]);

        $deadLettered = WorkflowRun::deadLetter()->get();
        $this->assertCount(1, $deadLettered);
        $this->assertTrue((bool) $deadLettered->first()->is_dead_letter);
    }

    public function test_retryable_scope(): void
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

        // Retryable: failed, not dead-lettered, next_retry_at in the past
        WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'is_dead_letter' => false,
            'next_retry_at' => now()->subMinute(),
            'idempotency_key' => 'retry-scope-1',
        ]);

        // Not retryable: dead-lettered
        WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'is_dead_letter' => true,
            'idempotency_key' => 'retry-scope-2',
        ]);

        $retryable = WorkflowRun::retryable()->get();
        $this->assertCount(1, $retryable);
    }

    // ─────────────────────────────────────────────────────────
    //  PARENT/CHILD RUN RELATIONSHIPS
    // ─────────────────────────────────────────────────────────

    public function test_parent_child_run_relationship(): void
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

        $parent = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
            'is_dead_letter' => true,
            'idempotency_key' => 'parent-run-1',
        ]);

        $child = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'completed',
            'triggered_by' => $this->user->id,
            'parent_run_id' => $parent->id,
            'idempotency_key' => 'child-run-1',
        ]);

        $this->assertEquals($parent->id, $child->parentRun->id);
        $this->assertCount(1, $parent->childRuns);
        $this->assertEquals($child->id, $parent->childRuns->first()->id);
    }
}
