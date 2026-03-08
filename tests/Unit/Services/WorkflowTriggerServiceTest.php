<?php

namespace Tests\Unit\Services;

use App\Jobs\ExecuteWorkflowRunJob;
use App\Models\User;
use App\Models\Branch;
use App\Models\UserLevel;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Services\WorkflowTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkflowTriggerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowTriggerService $triggerService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->triggerService = app(WorkflowTriggerService::class);

        $level = UserLevel::create(['name' => 'superadmin', 'description' => 'Super Admin']);
        $this->user = User::factory()->create(['user_level_id' => $level->id]);
        Branch::factory()->create(['is_main' => true, 'is_archived' => false]);
    }

    protected function createActiveWorkflow(string $triggerType, array $triggerConfig = [], array $actionConfig = []): WorkflowDefinition
    {
        $workflow = WorkflowDefinition::create([
            'name' => "Test Workflow ({$triggerType})",
            'status' => 'active',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);

        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'published',
            'published_by' => $this->user->id,
            'published_at' => now(),
        ]);

        WorkflowNode::create([
            'workflow_version_id' => $version->id,
            'node_id' => 'trigger_1',
            'type' => 'trigger',
            'action_type' => $triggerType,
            'label' => 'Trigger',
            'config' => $triggerConfig,
        ]);

        WorkflowNode::create([
            'workflow_version_id' => $version->id,
            'node_id' => 'action_1',
            'type' => 'action',
            'action_type' => 'notify',
            'label' => 'Notify',
            'config' => array_merge(['message' => 'Triggered'], $actionConfig),
        ]);

        WorkflowEdge::create([
            'workflow_version_id' => $version->id,
            'source_node_id' => 'trigger_1',
            'target_node_id' => 'action_1',
        ]);

        return $workflow;
    }

    public function test_fire_matches_low_stock_trigger(): void
    {
        $this->createActiveWorkflow('low_stock_reached', ['threshold' => 10]);

        $runs = $this->triggerService->fire('low_stock_reached', ['quantity' => 5], $this->user->id, false);

        $this->assertCount(1, $runs);
        // When async=false, the run is executed synchronously, so it completes
        $this->assertContains($runs[0]->status, ['completed', 'failed']);
    }

    public function test_fire_skips_when_above_threshold(): void
    {
        $this->createActiveWorkflow('low_stock_reached', ['threshold' => 10]);

        // Quantity above threshold should not match
        $runs = $this->triggerService->fire('low_stock_reached', ['quantity' => 15], $this->user->id, false);

        $this->assertCount(0, $runs);
    }

    public function test_fire_does_not_match_inactive_workflows(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Inactive WF',
            'status' => 'disabled',
            'created_by' => $this->user->id,
            'current_version' => 1,
        ]);

        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version_number' => 1,
            'status' => 'published',
            'published_by' => $this->user->id,
            'published_at' => now(),
        ]);

        WorkflowNode::create([
            'workflow_version_id' => $version->id,
            'node_id' => 'trigger_1',
            'type' => 'trigger',
            'action_type' => 'low_stock_reached',
            'label' => 'Trigger',
            'config' => [],
        ]);

        WorkflowNode::create([
            'workflow_version_id' => $version->id,
            'node_id' => 'action_1',
            'type' => 'action',
            'action_type' => 'notify',
            'label' => 'Notify',
            'config' => ['message' => 'Test'],
        ]);

        WorkflowEdge::create([
            'workflow_version_id' => $version->id,
            'source_node_id' => 'trigger_1',
            'target_node_id' => 'action_1',
        ]);

        $runs = $this->triggerService->fire('low_stock_reached', ['quantity' => 5], $this->user->id, false);
        $this->assertCount(0, $runs);
    }

    public function test_fire_respects_concurrency_limit(): void
    {
        $workflow = $this->createActiveWorkflow('order_approved');
        $workflow->update(['max_concurrency' => 1]);

        $version = $workflow->versions()->first();

        // Create an already-running run
        WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'status' => 'running',
            'triggered_by' => $this->user->id,
            'idempotency_key' => 'blocking-run',
        ]);

        $runs = $this->triggerService->fire('order_approved', [], $this->user->id, false);
        $this->assertCount(0, $runs);
    }

    public function test_fire_matches_multiple_workflows(): void
    {
        $this->createActiveWorkflow('stock_received');
        $this->createActiveWorkflow('stock_received');

        $runs = $this->triggerService->fire('stock_received', ['quantity' => 100], $this->user->id, false);
        $this->assertCount(2, $runs);
    }

    public function test_fire_does_not_match_wrong_trigger_type(): void
    {
        $this->createActiveWorkflow('order_approved');

        $runs = $this->triggerService->fire('low_stock_reached', ['quantity' => 5], $this->user->id, false);
        $this->assertCount(0, $runs);
    }

    public function test_fire_creates_run_with_correct_payload(): void
    {
        $this->createActiveWorkflow('stock_received');

        $payload = ['quantity' => 100, 'product_id' => 42, 'branch_id' => 1];
        $runs = $this->triggerService->fire('stock_received', $payload, $this->user->id, false);

        $this->assertCount(1, $runs);
        $run = $runs[0];
        $this->assertEquals('stock_received', $run->trigger_type);
        $this->assertEquals($payload, $run->trigger_payload);
        $this->assertEquals($this->user->id, $run->triggered_by);
    }

    public function test_fire_persists_matching_trigger_config_into_run_context(): void
    {
        Queue::fake();
        $this->createActiveWorkflow('low_stock_reached', ['threshold' => 9]);

        $runs = $this->triggerService->fire('low_stock_reached', ['quantity' => 3], $this->user->id, true);

        $this->assertCount(1, $runs);
        $run = $runs[0]->fresh();
        $this->assertEquals(9, data_get($run->context, '_workflow_trigger_config.threshold'));
        Queue::assertPushed(ExecuteWorkflowRunJob::class, 1);
    }

    public function test_fire_uses_fallback_actor_when_user_id_is_missing(): void
    {
        $this->createActiveWorkflow('order_created');

        $runs = $this->triggerService->fire('order_created', ['order_id' => 1001], null, false);

        $this->assertCount(1, $runs);
        $this->assertEquals($this->user->id, $runs[0]->triggered_by);
        $this->assertContains($runs[0]->status, ['completed', 'failed']);
    }

    public function test_fire_scheduled_workflows_dispatches_single_run_per_matching_workflow(): void
    {
        Queue::fake();
        $cron = now()->minute . ' ' . now()->hour . ' * * *';
        $this->createActiveWorkflow('daily_schedule', ['cron' => $cron]);
        $this->createActiveWorkflow('daily_schedule', ['cron' => $cron]);

        $runs = $this->triggerService->fireScheduledWorkflows();

        $this->assertCount(2, $runs);
        $this->assertEquals(2, WorkflowRun::query()->count());
        Queue::assertPushed(ExecuteWorkflowRunJob::class, 2);
    }

    public function test_fire_scheduled_workflows_respects_individual_cron_matching(): void
    {
        Queue::fake();
        $matchingCron = now()->minute . ' ' . now()->hour . ' * * *';
        $nonMatchingMinute = (now()->minute + 1) % 60;
        $nonMatchingCron = $nonMatchingMinute . ' ' . now()->hour . ' * * *';

        $this->createActiveWorkflow('daily_schedule', ['cron' => $matchingCron]);
        $this->createActiveWorkflow('daily_schedule', ['cron' => $nonMatchingCron]);

        $runs = $this->triggerService->fireScheduledWorkflows();

        $this->assertCount(1, $runs);
        Queue::assertPushed(ExecuteWorkflowRunJob::class, 1);
    }
}
