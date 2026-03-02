<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed if no workflows exist
        if (WorkflowDefinition::count() > 0) {
            return;
        }

        $userId = \App\Models\User::first()?->id ?? 1;

        // 1. Low Stock → Notify + Create Reorder Suggestion
        $wf1 = WorkflowDefinition::create([
            'name' => 'Low Stock Alert & Reorder',
            'description' => 'When stock is low, send notification and create a reorder suggestion.',
            'status' => 'active',
            'created_by' => $userId,
            'current_version' => 1,
        ]);

        $v1 = WorkflowVersion::create([
            'workflow_definition_id' => $wf1->id,
            'version_number' => 1,
            'status' => 'published',
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        $v1->nodes()->createMany([
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock Reached', 'config' => ['threshold' => 10], 'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Admin', 'config' => ['message' => 'Low stock alert: check inventory levels.'], 'position' => ['x' => 250, 'y' => 200]],
            ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'create_reorder_suggestion', 'label' => 'Create Reorder', 'config' => ['quantity' => 50], 'position' => ['x' => 450, 'y' => 200]],
        ]);
        $v1->edges()->createMany([
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_2'],
        ]);

        // 2. Expiry Soon → Hold + Notify + Report
        $wf2 = WorkflowDefinition::create([
            'name' => 'Expiry Alert & Hold',
            'description' => 'When batches are near expiry, hold them, notify staff, and generate report.',
            'status' => 'active',
            'created_by' => $userId,
            'current_version' => 1,
        ]);

        $v2 = WorkflowVersion::create([
            'workflow_definition_id' => $wf2->id,
            'version_number' => 1,
            'status' => 'published',
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        $v2->nodes()->createMany([
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'expiry_in_x_days', 'label' => 'Expiry in 30 Days', 'config' => ['days' => 30], 'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'create_hold', 'label' => 'Hold Batch', 'config' => ['reason' => 'Near expiry - quarantine'], 'position' => ['x' => 150, 'y' => 200]],
            ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Staff', 'config' => ['message' => 'Batches near expiry have been held.'], 'position' => ['x' => 350, 'y' => 200]],
            ['node_id' => 'action_3', 'type' => 'action', 'action_type' => 'generate_report', 'label' => 'Expiry Report', 'config' => ['report_type' => 'expiry_report'], 'position' => ['x' => 550, 'y' => 200]],
        ]);
        $v2->edges()->createMany([
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_2'],
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_3'],
        ]);

        // 3. Order Approved → Reserve Stock (FEFO)
        $wf3 = WorkflowDefinition::create([
            'name' => 'Order Approved → Auto Allocate',
            'description' => 'When order is approved, auto allocate stock using FEFO and log.',
            'status' => 'draft',
            'created_by' => $userId,
            'current_version' => 1,
        ]);

        $v3 = WorkflowVersion::create([
            'workflow_definition_id' => $wf3->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $v3->nodes()->createMany([
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'order_approved', 'label' => 'Order Approved', 'config' => [], 'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'auto_allocate_order', 'label' => 'FEFO Allocate', 'config' => [], 'position' => ['x' => 200, 'y' => 200]],
            ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Allocation', 'config' => ['message' => 'Order stock allocated via FEFO.'], 'position' => ['x' => 450, 'y' => 200]],
        ]);
        $v3->edges()->createMany([
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'action_1', 'target_node_id' => 'action_2'],
        ]);

        // 4. Daily Schedule → Stock Movement Report
        $wf4 = WorkflowDefinition::create([
            'name' => 'Daily Stock Movement Report',
            'description' => 'Generates daily stock movement report and sends notification.',
            'status' => 'draft',
            'created_by' => $userId,
            'current_version' => 1,
        ]);

        $v4 = WorkflowVersion::create([
            'workflow_definition_id' => $wf4->id,
            'version_number' => 1,
            'status' => 'draft',
        ]);

        $v4->nodes()->createMany([
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'daily_schedule', 'label' => 'Daily at 8AM', 'config' => ['cron' => '0 8 * * *'], 'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'generate_report', 'label' => 'Movement Report', 'config' => ['report_type' => 'stock_movement'], 'position' => ['x' => 200, 'y' => 200]],
            ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Report Ready', 'config' => ['message' => 'Daily stock movement report is ready.'], 'position' => ['x' => 450, 'y' => 200]],
        ]);
        $v4->edges()->createMany([
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'action_1', 'target_node_id' => 'action_2'],
        ]);
    }
}
