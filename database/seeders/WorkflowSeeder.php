<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Models\WorkflowPermission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkflowSeeder extends Seeder
{
    private int $primaryUserId;
    private int $secondaryUserId;
    private ?int $branchId;
    private ?int $secondBranchId;
    private ?int $productId;

    public function run(): void
    {
        if (WorkflowDefinition::count() > 0) {
            return;
        }

        // Resolve real entities for realistic references
        $this->primaryUserId   = User::first()?->id ?? 1;
        $this->secondaryUserId = User::where('id', '!=', $this->primaryUserId)->first()?->id ?? $this->primaryUserId;

        $mainBranch            = Branch::active()->main()->first();
        $this->branchId        = $mainBranch?->id;
        $this->secondBranchId  = Branch::active()->where('id', '!=', $this->branchId)->first()?->id ?? $this->branchId;

        $product               = Product::where('is_archived', false)->first();
        $this->productId       = $product?->id;

        // ═══════════════════════════════════════════════════════════
        //  1. Low Stock → Condition → Notify + Reorder  (3 versions)
        // ═══════════════════════════════════════════════════════════
        $wf1 = $this->createWorkflow1();

        // ═══════════════════════════════════════════════════════════
        //  2. Expiry Alert → Hold + Notify + Report
        // ═══════════════════════════════════════════════════════════
        $wf2 = $this->createWorkflow2();

        // ═══════════════════════════════════════════════════════════
        //  3. Order Approved → Category Gate → FEFO Allocate
        // ═══════════════════════════════════════════════════════════
        $wf3 = $this->createWorkflow3();

        // ═══════════════════════════════════════════════════════════
        //  4. Daily 8 AM → Stock Movement Report
        // ═══════════════════════════════════════════════════════════
        $wf4 = $this->createWorkflow4();

        // ═══════════════════════════════════════════════════════════
        //  5. Stock Received → Transfer + Webhook (draft)
        // ═══════════════════════════════════════════════════════════
        $wf5 = $this->createWorkflow5();

        // ═══════════════════════════════════════════════════════════
        //  6. Disabled monthly summary (archived)
        // ═══════════════════════════════════════════════════════════
        $wf6 = $this->createWorkflow6();

        // ═══════════════════════════════════════════════════════════
        //  7. Order Created → Multi-branch Dispatch
        // ═══════════════════════════════════════════════════════════
        $wf7 = $this->createWorkflow7();

        // ═══════════════════════════════════════════════════════════
        //  8. Weekly Inventory Audit (scheduled)
        // ═══════════════════════════════════════════════════════════
        $wf8 = $this->createWorkflow8();

        // ═══════════════════════════════════════════════════════════
        //  Permissions  (varied per workflow)
        // ═══════════════════════════════════════════════════════════
        $this->seedPermissions($wf1, ['view', 'edit', 'publish', 'run'], ['view', 'run']);
        $this->seedPermissions($wf2, ['view', 'edit', 'run'],            ['view']);
        $this->seedPermissions($wf3, ['view', 'edit', 'publish', 'run'], ['view', 'edit']);
        $this->seedPermissions($wf4, ['view', 'run'],                    ['view']);
        $this->seedPermissions($wf7, ['view', 'edit', 'run'],            ['view', 'run']);
    }

    // ---------------------------------------------------------------
    //  Workflow 1 — Low Stock Alert & Reorder (3 versions)
    // ---------------------------------------------------------------
    private function createWorkflow1(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'            => 'Low Stock Alert & Reorder',
            'description'     => 'Monitors inventory levels. When stock falls below threshold, evaluates severity and sends notification or creates a reorder suggestion.',
            'status'          => 'active',
            'created_by'      => $this->primaryUserId,
            'updated_by'      => $this->primaryUserId,
            'branch_id'       => $this->branchId,
            'current_version' => 3,
            'max_concurrency' => 5,
        ]);

        // --- v1 (archived): trigger → notify only -----------------
        $v1 = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 1,
            'status'          => 'archived',
            'change_summary'  => 'Initial version — send notification on low stock',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subDays(21),
        ]);
        $this->buildNodes($v1, [
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'low_stock_reached', 'label' => 'Low Stock Reached', 'config' => ['threshold' => 15], 'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1',  'type' => 'action',  'action_type' => 'notify',            'label' => 'Notify Admin',      'config' => ['message' => 'Low stock alert.'], 'position' => ['x' => 300, 'y' => 200]],
        ]);
        $this->buildEdges($v1, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
        ]);

        // --- v2 (archived): added threshold condition + reorder ---
        $v2 = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 2,
            'status'          => 'archived',
            'change_summary'  => 'Added quantity condition gate and reorder suggestion action',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subDays(7),
        ]);
        $this->buildNodes($v2, [
            ['node_id' => 'trigger_1', 'type' => 'trigger',   'action_type' => 'low_stock_reached',       'label' => 'Low Stock Reached', 'config' => ['threshold' => 10],                        'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'cond_1',    'type' => 'condition',  'action_type' => 'quantity_threshold',      'label' => 'Qty < 5?',          'config' => ['operator' => '<', 'value' => 5],           'position' => ['x' => 300, 'y' => 160]],
            ['node_id' => 'action_1',  'type' => 'action',     'action_type' => 'notify',                  'label' => 'Notify Admin',      'config' => ['message' => 'Critical low stock alert.'],  'position' => ['x' => 150, 'y' => 300]],
            ['node_id' => 'action_2',  'type' => 'action',     'action_type' => 'create_reorder_suggestion','label' => 'Create Reorder',   'config' => ['quantity' => 50],                          'position' => ['x' => 450, 'y' => 300]],
        ]);
        $this->buildEdges($v2, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'cond_1'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_1', 'condition_branch' => 'true'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_2', 'condition_branch' => 'true'],
        ]);

        // --- v3 (published / current): added audit log + refined condition ---
        $v3 = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 3,
            'status'          => 'published',
            'change_summary'  => 'Added audit log step, refined threshold to <= 3, increased reorder quantity to 100',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subDay(),
        ]);
        $this->buildNodes($v3, [
            ['node_id' => 'trigger_1', 'type' => 'trigger',   'action_type' => 'low_stock_reached',        'label' => 'Low Stock Reached',    'config' => ['threshold' => 10],                                                                     'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'cond_1',    'type' => 'condition',  'action_type' => 'quantity_threshold',       'label' => 'Qty ≤ 3?',            'config' => ['operator' => '<=', 'value' => 3],                                                      'position' => ['x' => 300, 'y' => 160]],
            ['node_id' => 'action_1',  'type' => 'action',     'action_type' => 'notify',                   'label' => 'Emergency Notify',     'config' => ['message' => 'CRITICAL: Quantity ≤ 3 — immediate attention required.'],                'position' => ['x' => 100, 'y' => 310]],
            ['node_id' => 'action_2',  'type' => 'action',     'action_type' => 'create_reorder_suggestion','label' => 'Reorder 100 units',    'config' => ['quantity' => 100],                                                                     'position' => ['x' => 300, 'y' => 310]],
            ['node_id' => 'action_3',  'type' => 'action',     'action_type' => 'log_audit_event',          'label' => 'Audit Log',            'config' => ['message' => 'Low-stock workflow executed. Reorder created.'],                           'position' => ['x' => 500, 'y' => 310]],
            ['node_id' => 'action_4',  'type' => 'action',     'action_type' => 'notify',                   'label' => 'Log Not Critical',     'config' => ['message' => 'Stock is low but above critical threshold — monitoring.'],               'position' => ['x' => 300, 'y' => 460]],
        ]);
        $this->buildEdges($v3, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'cond_1'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_1', 'condition_branch' => 'true'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_2', 'condition_branch' => 'true'],
            ['source_node_id' => 'action_2',  'target_node_id' => 'action_3'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_4', 'condition_branch' => 'false'],
        ]);

        // Runs for wf1
        $this->seedRunsForWorkflow1($wf, $v3);

        return $wf;
    }

    // ---------------------------------------------------------------
    //  Workflow 2 — Expiry Alert & Hold
    // ---------------------------------------------------------------
    private function createWorkflow2(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'            => 'Expiry Alert & Hold',
            'description'     => 'When batches are within 30 days of expiry, quarantine them, notify pharmacy staff, and generate an expiry report.',
            'status'          => 'active',
            'created_by'      => $this->primaryUserId,
            'branch_id'       => $this->branchId,
            'current_version' => 1,
            'max_concurrency' => 3,
        ]);

        $v = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number' => 1,
            'status'         => 'published',
            'change_summary' => 'Hold near-expiry batches, notify staff, generate report',
            'published_by'   => $this->primaryUserId,
            'published_at'   => now()->subDays(14),
        ]);
        $this->buildNodes($v, [
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'expiry_in_x_days', 'label' => 'Expiry ≤ 30 Days',    'config' => ['days' => 30],                                                      'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1',  'type' => 'action',  'action_type' => 'create_hold',      'label' => 'Quarantine Batch',     'config' => ['reason' => 'Near expiry — quarantine per SOP-PH-041'],             'position' => ['x' => 100, 'y' => 220]],
            ['node_id' => 'action_2',  'type' => 'action',  'action_type' => 'notify',           'label' => 'Notify Pharmacy',      'config' => ['message' => 'Batches within 30 days of expiry have been held.'],   'position' => ['x' => 300, 'y' => 220]],
            ['node_id' => 'action_3',  'type' => 'action',  'action_type' => 'generate_report',  'label' => 'Expiry Report',        'config' => ['report_type' => 'expiry_report'],                                  'position' => ['x' => 500, 'y' => 220]],
            ['node_id' => 'action_4',  'type' => 'action',  'action_type' => 'log_audit_event',  'label' => 'Audit Trail',          'config' => ['message' => 'Expiry workflow completed — batches quarantined.'],   'position' => ['x' => 300, 'y' => 380]],
        ]);
        $this->buildEdges($v, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_2'],
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_3'],
            ['source_node_id' => 'action_1',  'target_node_id' => 'action_4'],
        ]);

        // A few runs for wf2
        $this->seedRunsForWorkflow2($wf, $v);

        return $wf;
    }

    // ---------------------------------------------------------------
    //  Workflow 3 — Order Approved → Category Gate → FEFO Allocation
    // ---------------------------------------------------------------
    private function createWorkflow3(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'            => 'Order Approved → Auto Allocate',
            'description'     => 'When an order is approved, check if products are pharmaceuticals then auto-allocate using FEFO strategy. Non-pharma orders get audit-logged and skipped.',
            'status'          => 'active',
            'created_by'      => $this->primaryUserId,
            'branch_id'       => $this->branchId,
            'current_version' => 1,
            'max_concurrency' => 10,
        ]);

        $v = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 1,
            'status'          => 'published',
            'change_summary'  => 'Category gate with FEFO allocation and skip log',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subDays(10),
        ]);
        $this->buildNodes($v, [
            ['node_id' => 'trigger_1','type' => 'trigger',   'action_type' => 'order_approved',       'label' => 'Order Approved',    'config' => [],                                                        'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'cond_1',   'type' => 'condition',  'action_type' => 'category_matches',    'label' => 'Is Pharma?',        'config' => ['categories' => ['pharmaceuticals']],                     'position' => ['x' => 300, 'y' => 170]],
            ['node_id' => 'action_1', 'type' => 'action',     'action_type' => 'auto_allocate_order', 'label' => 'FEFO Allocate',     'config' => [],                                                        'position' => ['x' => 120, 'y' => 320]],
            ['node_id' => 'action_2', 'type' => 'action',     'action_type' => 'notify',              'label' => 'Confirm Allocated', 'config' => ['message' => 'Order allocated via FEFO.'],                'position' => ['x' => 120, 'y' => 460]],
            ['node_id' => 'action_3', 'type' => 'action',     'action_type' => 'log_audit_event',     'label' => 'Log Skip',          'config' => ['message' => 'Order skipped — non-pharmaceutical.'],      'position' => ['x' => 480, 'y' => 320]],
        ]);
        $this->buildEdges($v, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'cond_1'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_1', 'condition_branch' => 'true'],
            ['source_node_id' => 'action_1',  'target_node_id' => 'action_2'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_3', 'condition_branch' => 'false'],
        ]);

        $this->seedRunsForWorkflow3($wf, $v);

        return $wf;
    }

    // ---------------------------------------------------------------
    //  Workflow 4 — Daily Stock Movement Report
    // ---------------------------------------------------------------
    private function createWorkflow4(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'            => 'Daily Stock Movement Report',
            'description'     => 'Generates a stock movement report and emails staff every day at 08:00.',
            'status'          => 'active',
            'created_by'      => $this->primaryUserId,
            'current_version' => 1,
        ]);

        $v = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 1,
            'status'          => 'published',
            'change_summary'  => 'Daily cron: generate report then notify',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subDays(30),
        ]);
        $this->buildNodes($v, [
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'daily_schedule',  'label' => 'Daily 08:00',          'config' => ['cron' => '0 8 * * *'],                                    'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1',  'type' => 'action',  'action_type' => 'generate_report', 'label' => 'Stock Movement Rpt',   'config' => ['report_type' => 'stock_movement'],                         'position' => ['x' => 200, 'y' => 210]],
            ['node_id' => 'action_2',  'type' => 'action',  'action_type' => 'notify',          'label' => 'Email Report Ready',   'config' => ['message' => 'Daily stock movement report is ready.'],       'position' => ['x' => 450, 'y' => 210]],
            ['node_id' => 'action_3',  'type' => 'action',  'action_type' => 'log_audit_event', 'label' => 'Log Execution',        'config' => ['message' => 'Daily stock movement report generated.'],     'position' => ['x' => 330, 'y' => 360]],
        ]);
        $this->buildEdges($v, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'action_1',  'target_node_id' => 'action_2'],
            ['source_node_id' => 'action_1',  'target_node_id' => 'action_3'],
        ]);

        // Seed a history of scheduled runs (last 5 days)
        $this->seedScheduledRunHistory($wf, $v, 5);

        return $wf;
    }

    // ---------------------------------------------------------------
    //  Workflow 5 — Stock Received → Transfer + Webhook (DRAFT)
    // ---------------------------------------------------------------
    private function createWorkflow5(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'              => 'Stock Received → Transfer & Notify External',
            'description'       => 'On stock receipt, auto-create a transfer request to the satellite branch and call an external webhook for ERP sync.',
            'status'            => 'draft',
            'created_by'        => $this->primaryUserId,
            'branch_id'         => $this->branchId,
            'current_version'   => 1,
            'max_concurrency'   => 2,
            'webhook_allowlist' => ['https://erp.example.com/*', 'https://api.example.com/*'],
            'webhook_secret'    => Str::random(40),
        ]);

        $v = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 1,
            'status'          => 'draft',
            'change_summary'  => 'Draft v1 — transfer + external webhook call',
        ]);
        $destBranchId = $this->secondBranchId ?? 2;
        $this->buildNodes($v, [
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'stock_received',           'label' => 'Stock Received',    'config' => [],                                                                             'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1',  'type' => 'action',  'action_type' => 'create_transfer_request',  'label' => 'Transfer Request',  'config' => ['target_branch_id' => $destBranchId],                                           'position' => ['x' => 130, 'y' => 220]],
            ['node_id' => 'action_2',  'type' => 'action',  'action_type' => 'webhook_call',             'label' => 'ERP Sync Webhook',  'config' => ['url' => 'https://erp.example.com/api/stock-sync', 'method' => 'POST'],         'position' => ['x' => 470, 'y' => 220]],
            ['node_id' => 'action_3',  'type' => 'action',  'action_type' => 'notify',                   'label' => 'Confirm Done',      'config' => ['message' => 'Transfer request created and ERP notified.'],                    'position' => ['x' => 300, 'y' => 380]],
        ]);
        $this->buildEdges($v, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_2'],
            ['source_node_id' => 'action_1',  'target_node_id' => 'action_3'],
            ['source_node_id' => 'action_2',  'target_node_id' => 'action_3'],
        ]);

        return $wf;
    }

    // ---------------------------------------------------------------
    //  Workflow 6 — Disabled / Archived Monthly Summary
    // ---------------------------------------------------------------
    private function createWorkflow6(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'            => 'Archived — Old Monthly Summary',
            'description'     => 'Previously active monthly summary workflow that has been disabled. Retained for audit trail.',
            'status'          => 'disabled',
            'created_by'      => $this->primaryUserId,
            'current_version' => 1,
        ]);

        $v = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 1,
            'status'          => 'archived',
            'change_summary'  => 'Disabled by admin — replaced by daily report workflow',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subMonths(2),
        ]);
        $this->buildNodes($v, [
            ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'daily_schedule',  'label' => 'Monthly 1st 00:00', 'config' => ['cron' => '0 0 1 * *'],            'position' => ['x' => 300, 'y' => 50]],
            ['node_id' => 'action_1',  'type' => 'action',  'action_type' => 'generate_report', 'label' => 'Monthly Summary',   'config' => ['report_type' => 'monthly_summary'],'position' => ['x' => 300, 'y' => 200]],
        ]);
        $this->buildEdges($v, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
        ]);

        return $wf;
    }

    // ---------------------------------------------------------------
    //  Workflow 7 — Order Created → Multi-branch Dispatch (NEW)
    // ---------------------------------------------------------------
    private function createWorkflow7(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'            => 'Order Created → Multi-branch Dispatch',
            'description'     => 'When a new order is created, check stock levels across branches, create inter-branch transfer if needed, and notify relevant parties.',
            'status'          => 'active',
            'created_by'      => $this->primaryUserId,
            'branch_id'       => $this->branchId,
            'current_version' => 1,
            'max_concurrency' => 8,
        ]);

        $v = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 1,
            'status'          => 'published',
            'change_summary'  => 'Multi-branch stock check with conditional transfer and notification chain',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subDays(3),
        ]);
        $this->buildNodes($v, [
            ['node_id' => 'trigger_1', 'type' => 'trigger',   'action_type' => 'order_created',          'label' => 'Order Created',             'config' => [],                                                                                'position' => ['x' => 350, 'y' => 40]],
            ['node_id' => 'cond_1',    'type' => 'condition',  'action_type' => 'quantity_threshold',     'label' => 'Order Qty > 50?',           'config' => ['operator' => '>', 'value' => 50],                                                'position' => ['x' => 350, 'y' => 160]],
            ['node_id' => 'action_1',  'type' => 'action',     'action_type' => 'create_transfer_request','label' => 'Inter-branch Transfer',     'config' => ['target_branch_id' => $this->secondBranchId ?? 1],                                 'position' => ['x' => 140, 'y' => 310]],
            ['node_id' => 'action_2',  'type' => 'action',     'action_type' => 'notify',                 'label' => 'Alert Warehouse',           'config' => ['message' => 'Large order received — inter-branch transfer initiated.'],           'position' => ['x' => 140, 'y' => 460]],
            ['node_id' => 'action_3',  'type' => 'action',     'action_type' => 'auto_allocate_order',    'label' => 'Local Allocate',            'config' => [],                                                                                'position' => ['x' => 550, 'y' => 310]],
            ['node_id' => 'action_4',  'type' => 'action',     'action_type' => 'log_audit_event',        'label' => 'Log Fulfillment',           'config' => ['message' => 'Order fulfilled locally — no transfer needed.'],                    'position' => ['x' => 550, 'y' => 460]],
        ]);
        $this->buildEdges($v, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'cond_1'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_1', 'condition_branch' => 'true'],
            ['source_node_id' => 'action_1',  'target_node_id' => 'action_2'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_3', 'condition_branch' => 'false'],
            ['source_node_id' => 'action_3',  'target_node_id' => 'action_4'],
        ]);

        $this->seedRunsForWorkflow7($wf, $v);

        return $wf;
    }

    // ---------------------------------------------------------------
    //  Workflow 8 — Weekly Inventory Audit (NEW)
    // ---------------------------------------------------------------
    private function createWorkflow8(): WorkflowDefinition
    {
        $wf = WorkflowDefinition::create([
            'name'            => 'Weekly Inventory Audit',
            'description'     => 'Every Monday at 06:00, generate a full inventory report, compare thresholds, and send audit notification to management.',
            'status'          => 'active',
            'created_by'      => $this->secondaryUserId,
            'updated_by'      => $this->primaryUserId,
            'current_version' => 1,
            'max_concurrency' => 1,
        ]);

        $v = WorkflowVersion::create([
            'workflow_definition_id' => $wf->id,
            'version_number'  => 1,
            'status'          => 'published',
            'change_summary'  => 'Weekly audit: inventory report + threshold check + management notification',
            'published_by'    => $this->primaryUserId,
            'published_at'    => now()->subDays(5),
        ]);
        $this->buildNodes($v, [
            ['node_id' => 'trigger_1', 'type' => 'trigger',   'action_type' => 'daily_schedule',     'label' => 'Monday 06:00',          'config' => ['cron' => '0 6 * * 1'],                                              'position' => ['x' => 300, 'y' => 40]],
            ['node_id' => 'action_1',  'type' => 'action',     'action_type' => 'generate_report',   'label' => 'Full Inventory Rpt',    'config' => ['report_type' => 'inventory_audit'],                                  'position' => ['x' => 300, 'y' => 190]],
            ['node_id' => 'cond_1',    'type' => 'condition',  'action_type' => 'quantity_threshold', 'label' => 'Any Items ≤ 5?',        'config' => ['operator' => '<=', 'value' => 5],                                    'position' => ['x' => 300, 'y' => 340]],
            ['node_id' => 'action_2',  'type' => 'action',     'action_type' => 'notify',            'label' => 'Urgent Mgmt Alert',     'config' => ['message' => 'Weekly audit: critical stock levels detected.'],        'position' => ['x' => 120, 'y' => 490]],
            ['node_id' => 'action_3',  'type' => 'action',     'action_type' => 'notify',            'label' => 'Routine Confirmation',  'config' => ['message' => 'Weekly inventory audit completed. All levels normal.'], 'position' => ['x' => 480, 'y' => 490]],
            ['node_id' => 'action_4',  'type' => 'action',     'action_type' => 'log_audit_event',   'label' => 'Log Audit',             'config' => ['message' => 'Weekly inventory audit cycle completed.'],              'position' => ['x' => 300, 'y' => 630]],
        ]);
        $this->buildEdges($v, [
            ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
            ['source_node_id' => 'action_1',  'target_node_id' => 'cond_1'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_2', 'condition_branch' => 'true'],
            ['source_node_id' => 'cond_1',    'target_node_id' => 'action_3', 'condition_branch' => 'false'],
            ['source_node_id' => 'action_2',  'target_node_id' => 'action_4'],
            ['source_node_id' => 'action_3',  'target_node_id' => 'action_4'],
        ]);

        $this->seedScheduledRunHistory($wf, $v, 3);

        return $wf;
    }

    // ==============================================================
    //  Run Seeders
    // ==============================================================

    /**
     * Workflow 1 runs — complete lifecycle: completed, failed, retried, dead-letter, rerun from DL, dry-run, cancelled
     */
    private function seedRunsForWorkflow1(WorkflowDefinition $wf, WorkflowVersion $v): void
    {
        $uid = $this->primaryUserId;
        $productRef = $this->productId ?? 1;

        // ---- Run A: Completed (manual) ----
        $runA = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $uid,
            'trigger_type'           => 'manual',
            'trigger_payload'        => ['quantity' => 2, 'product_id' => $productRef],
            'started_at'             => now()->subHours(6),
            'completed_at'           => now()->subHours(6)->addSeconds(3),
            'context'                => ['quantity' => 2, 'notification_sent' => true, 'reorder_created' => true],
            'idempotency_key'        => 'seed-wf1-run-completed',
        ]);
        $this->seedSteps($runA, [
            ['node_id' => 'trigger_1', 'action_type' => 'low_stock_reached',        'status' => 'completed', 'offset_ms' => 0,    'duration_ms' => 40],
            ['node_id' => 'cond_1',    'action_type' => 'quantity_threshold',        'status' => 'completed', 'offset_ms' => 40,   'duration_ms' => 20,  'output' => ['result' => true, 'branch' => 'true']],
            ['node_id' => 'action_1',  'action_type' => 'notify',                   'status' => 'completed', 'offset_ms' => 60,   'duration_ms' => 800, 'output' => ['recipients' => 2]],
            ['node_id' => 'action_2',  'action_type' => 'create_reorder_suggestion', 'status' => 'completed', 'offset_ms' => 60,   'duration_ms' => 500, 'output' => ['suggestion_id' => 42]],
            ['node_id' => 'action_3',  'action_type' => 'log_audit_event',           'status' => 'completed', 'offset_ms' => 570,  'duration_ms' => 30,  'output' => ['logged' => true]],
        ]);

        // ---- Run B: Failed on first attempt ----
        $runB = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'failed',
            'triggered_by'           => $uid,
            'trigger_type'           => 'event',
            'trigger_payload'        => ['quantity' => 1, 'product_id' => 9999],
            'started_at'             => now()->subHours(4),
            'completed_at'           => now()->subHours(4)->addSeconds(2),
            'error_message'          => 'Action create_reorder_suggestion failed: Product #9999 not found in active inventory',
            'context'                => ['quantity' => 1],
            'retry_attempt'          => 0,
            'max_retries'            => 3,
            'next_retry_at'          => now()->subHours(4)->addMinutes(2),
            'idempotency_key'        => 'seed-wf1-run-failed',
        ]);
        $this->seedSteps($runB, [
            ['node_id' => 'trigger_1', 'action_type' => 'low_stock_reached',        'status' => 'completed', 'offset_ms' => 0,    'duration_ms' => 35],
            ['node_id' => 'cond_1',    'action_type' => 'quantity_threshold',        'status' => 'completed', 'offset_ms' => 35,   'duration_ms' => 15, 'output' => ['result' => true, 'branch' => 'true']],
            ['node_id' => 'action_1',  'action_type' => 'notify',                   'status' => 'completed', 'offset_ms' => 50,   'duration_ms' => 600, 'output' => ['recipients' => 1]],
            ['node_id' => 'action_2',  'action_type' => 'create_reorder_suggestion', 'status' => 'failed',    'offset_ms' => 50,   'duration_ms' => 1500, 'error' => 'Product #9999 not found in active inventory'],
        ]);

        // ---- Run C: Retry attempt 2 → still failed ----
        $runC = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'failed',
            'triggered_by'           => $uid,
            'trigger_type'           => 'retry',
            'trigger_payload'        => ['quantity' => 1, 'product_id' => 9999],
            'started_at'             => now()->subHours(3)->subMinutes(50),
            'completed_at'           => now()->subHours(3)->subMinutes(50)->addSeconds(3),
            'error_message'          => 'Retry 2/3 — Product #9999 still not found',
            'context'                => ['quantity' => 1],
            'retry_attempt'          => 2,
            'max_retries'            => 3,
            'parent_run_id'          => $runB->id,
            'next_retry_at'          => now()->subHours(3)->subMinutes(40),
            'idempotency_key'        => 'seed-wf1-run-retry-2',
        ]);
        $this->seedSteps($runC, [
            ['node_id' => 'trigger_1', 'action_type' => 'low_stock_reached',        'status' => 'completed', 'offset_ms' => 0,   'duration_ms' => 30],
            ['node_id' => 'action_2',  'action_type' => 'create_reorder_suggestion', 'status' => 'failed',    'offset_ms' => 50,  'duration_ms' => 2500, 'error' => 'Product #9999 still not found'],
        ]);

        // ---- Run D: Dead-lettered (retries exhausted) ----
        $runD = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'failed',
            'triggered_by'           => $uid,
            'trigger_type'           => 'retry',
            'trigger_payload'        => ['quantity' => 1, 'product_id' => 9999],
            'started_at'             => now()->subHours(3),
            'completed_at'           => now()->subHours(3)->addSeconds(2),
            'error_message'          => 'Dead-lettered after 3 retries. Last error: Product #9999 not found',
            'context'                => ['quantity' => 1],
            'retry_attempt'          => 3,
            'max_retries'            => 3,
            'is_dead_letter'         => true,
            'parent_run_id'          => $runB->id,
            'idempotency_key'        => 'seed-wf1-run-deadletter',
        ]);
        $this->seedSteps($runD, [
            ['node_id' => 'trigger_1', 'action_type' => 'low_stock_reached',        'status' => 'completed', 'offset_ms' => 0,   'duration_ms' => 30],
            ['node_id' => 'action_2',  'action_type' => 'create_reorder_suggestion', 'status' => 'failed',    'offset_ms' => 40,  'duration_ms' => 1800, 'error' => 'Product #9999 not found — max retries exhausted'],
        ]);

        // ---- Run E: Rerun from dead-letter (success after data fix) ----
        $runE = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $uid,
            'trigger_type'           => 'rerun',
            'trigger_payload'        => ['quantity' => 1, 'product_id' => $productRef],
            'started_at'             => now()->subHours(2),
            'completed_at'           => now()->subHours(2)->addSeconds(4),
            'context'                => ['quantity' => 1, 'reorder_created' => true, 'rerun_of' => $runD->id],
            'parent_run_id'          => $runD->id,
            'idempotency_key'        => 'seed-wf1-run-rerun-success',
        ]);
        $this->seedSteps($runE, [
            ['node_id' => 'trigger_1', 'action_type' => 'low_stock_reached',        'status' => 'completed', 'offset_ms' => 0,    'duration_ms' => 35],
            ['node_id' => 'cond_1',    'action_type' => 'quantity_threshold',        'status' => 'completed', 'offset_ms' => 35,   'duration_ms' => 15, 'output' => ['result' => true, 'branch' => 'true']],
            ['node_id' => 'action_1',  'action_type' => 'notify',                   'status' => 'completed', 'offset_ms' => 50,   'duration_ms' => 750, 'output' => ['recipients' => 2]],
            ['node_id' => 'action_2',  'action_type' => 'create_reorder_suggestion', 'status' => 'completed', 'offset_ms' => 50,   'duration_ms' => 600, 'output' => ['suggestion_id' => 43]],
            ['node_id' => 'action_3',  'action_type' => 'log_audit_event',           'status' => 'completed', 'offset_ms' => 660,  'duration_ms' => 25,  'output' => ['logged' => true]],
        ]);

        // ---- Run F: Dry-run ----
        $runF = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $this->secondaryUserId,
            'trigger_type'           => 'manual',
            'trigger_payload'        => ['quantity' => 8, 'product_id' => $productRef],
            'started_at'             => now()->subMinutes(45),
            'completed_at'           => now()->subMinutes(45)->addMilliseconds(800),
            'is_dry_run'             => true,
            'context'                => ['quantity' => 8, 'dry_run' => true, 'would_execute' => ['notify', 'log_audit_event']],
            'idempotency_key'        => 'seed-wf1-run-dryrun',
        ]);
        $this->seedSteps($runF, [
            ['node_id' => 'trigger_1', 'action_type' => 'low_stock_reached',  'status' => 'completed', 'offset_ms' => 0,   'duration_ms' => 20],
            ['node_id' => 'cond_1',    'action_type' => 'quantity_threshold',  'status' => 'completed', 'offset_ms' => 20,  'duration_ms' => 10, 'output' => ['result' => false, 'branch' => 'false']],
            ['node_id' => 'action_4',  'action_type' => 'notify',             'status' => 'skipped',   'offset_ms' => 30,  'duration_ms' => 5,  'output' => ['dry_run' => true, 'skipped' => true]],
        ]);

        // ---- Run G: Cancelled mid-execution ----
        WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'cancelled',
            'triggered_by'           => $uid,
            'trigger_type'           => 'manual',
            'trigger_payload'        => ['quantity' => 4, 'product_id' => $productRef],
            'started_at'             => now()->subMinutes(15),
            'completed_at'           => now()->subMinutes(15)->addSeconds(1),
            'error_message'          => 'Cancelled by user before completion',
            'context'                => ['quantity' => 4, 'cancelled_at_node' => 'action_1'],
            'idempotency_key'        => 'seed-wf1-run-cancelled',
        ]);
    }

    /**
     * Workflow 2 runs — expiry workflow: 1 completed, 1 running
     */
    private function seedRunsForWorkflow2(WorkflowDefinition $wf, WorkflowVersion $v): void
    {
        // Completed run
        $run = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $this->primaryUserId,
            'trigger_type'           => 'scheduled',
            'trigger_payload'        => ['batch_count' => 3, 'expiry_window_days' => 30],
            'started_at'             => now()->subHours(12),
            'completed_at'           => now()->subHours(12)->addSeconds(6),
            'context'                => ['batches_held' => 3, 'report_generated' => true, 'notifications_sent' => 1],
            'idempotency_key'        => 'seed-wf2-run-completed',
        ]);
        $this->seedSteps($run, [
            ['node_id' => 'trigger_1', 'action_type' => 'expiry_in_x_days', 'status' => 'completed', 'offset_ms' => 0,    'duration_ms' => 60],
            ['node_id' => 'action_1',  'action_type' => 'create_hold',      'status' => 'completed', 'offset_ms' => 60,   'duration_ms' => 2000, 'output' => ['hold_ids' => [101, 102, 103]]],
            ['node_id' => 'action_2',  'action_type' => 'notify',           'status' => 'completed', 'offset_ms' => 60,   'duration_ms' => 900,  'output' => ['recipients' => 3]],
            ['node_id' => 'action_3',  'action_type' => 'generate_report',  'status' => 'completed', 'offset_ms' => 60,   'duration_ms' => 3500, 'output' => ['report_path' => 'reports/expiry-2026-03-03.xlsx']],
            ['node_id' => 'action_4',  'action_type' => 'log_audit_event',  'status' => 'completed', 'offset_ms' => 4000, 'duration_ms' => 30,   'output' => ['logged' => true]],
        ]);

        // Currently running (in-progress)
        $runInProgress = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'running',
            'triggered_by'           => $this->primaryUserId,
            'trigger_type'           => 'event',
            'trigger_payload'        => ['batch_count' => 1, 'expiry_window_days' => 30],
            'started_at'             => now()->subSeconds(10),
            'context'                => ['batches_held' => 0],
            'idempotency_key'        => 'seed-wf2-run-inprogress',
        ]);
        $this->seedSteps($runInProgress, [
            ['node_id' => 'trigger_1', 'action_type' => 'expiry_in_x_days', 'status' => 'completed', 'offset_ms' => 0,   'duration_ms' => 45],
            ['node_id' => 'action_1',  'action_type' => 'create_hold',      'status' => 'running',   'offset_ms' => 45,  'duration_ms' => null],
        ]);
    }

    /**
     * Workflow 3 runs — order allocation: 1 allocated, 1 skipped
     */
    private function seedRunsForWorkflow3(WorkflowDefinition $wf, WorkflowVersion $v): void
    {
        // Successful FEFO allocation
        $runAlloc = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $this->primaryUserId,
            'trigger_type'           => 'event',
            'trigger_payload'        => ['order_id' => 1001, 'category' => 'pharmaceuticals'],
            'started_at'             => now()->subHours(8),
            'completed_at'           => now()->subHours(8)->addSeconds(5),
            'context'                => ['order_id' => 1001, 'allocated' => true, 'strategy' => 'FEFO'],
            'idempotency_key'        => 'seed-wf3-run-allocated',
        ]);
        $this->seedSteps($runAlloc, [
            ['node_id' => 'trigger_1', 'action_type' => 'order_approved',       'status' => 'completed', 'offset_ms' => 0,    'duration_ms' => 30],
            ['node_id' => 'cond_1',    'action_type' => 'category_matches',     'status' => 'completed', 'offset_ms' => 30,   'duration_ms' => 20,   'output' => ['result' => true, 'branch' => 'true']],
            ['node_id' => 'action_1',  'action_type' => 'auto_allocate_order',  'status' => 'completed', 'offset_ms' => 50,   'duration_ms' => 3500, 'output' => ['allocated_batches' => 3, 'total_qty' => 200]],
            ['node_id' => 'action_2',  'action_type' => 'notify',              'status' => 'completed', 'offset_ms' => 3550,  'duration_ms' => 700,  'output' => ['recipients' => 1]],
        ]);

        // Non-pharma order skipped
        $runSkip = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $this->secondaryUserId,
            'trigger_type'           => 'event',
            'trigger_payload'        => ['order_id' => 1002, 'category' => 'office_supplies'],
            'started_at'             => now()->subHours(5),
            'completed_at'           => now()->subHours(5)->addSeconds(1),
            'context'                => ['order_id' => 1002, 'skipped' => true, 'reason' => 'non-pharmaceutical'],
            'idempotency_key'        => 'seed-wf3-run-skipped',
        ]);
        $this->seedSteps($runSkip, [
            ['node_id' => 'trigger_1', 'action_type' => 'order_approved',   'status' => 'completed', 'offset_ms' => 0,   'duration_ms' => 25],
            ['node_id' => 'cond_1',    'action_type' => 'category_matches', 'status' => 'completed', 'offset_ms' => 25,  'duration_ms' => 15, 'output' => ['result' => false, 'branch' => 'false']],
            ['node_id' => 'action_3',  'action_type' => 'log_audit_event',  'status' => 'completed', 'offset_ms' => 40,  'duration_ms' => 50, 'output' => ['logged' => true]],
        ]);
    }

    /**
     * Workflow 7 runs — multi-branch dispatch: 1 large order (transfer), 1 small (local)
     */
    private function seedRunsForWorkflow7(WorkflowDefinition $wf, WorkflowVersion $v): void
    {
        // Large order → transfer path
        $runLarge = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $this->primaryUserId,
            'trigger_type'           => 'event',
            'trigger_payload'        => ['order_id' => 2001, 'quantity' => 120],
            'started_at'             => now()->subHours(10),
            'completed_at'           => now()->subHours(10)->addSeconds(7),
            'context'                => ['order_id' => 2001, 'transfer_created' => true],
            'idempotency_key'        => 'seed-wf7-run-large',
        ]);
        $this->seedSteps($runLarge, [
            ['node_id' => 'trigger_1', 'action_type' => 'order_created',           'status' => 'completed', 'offset_ms' => 0,    'duration_ms' => 30],
            ['node_id' => 'cond_1',    'action_type' => 'quantity_threshold',      'status' => 'completed', 'offset_ms' => 30,   'duration_ms' => 10,   'output' => ['result' => true, 'branch' => 'true']],
            ['node_id' => 'action_1',  'action_type' => 'create_transfer_request', 'status' => 'completed', 'offset_ms' => 40,   'duration_ms' => 4000, 'output' => ['transfer_id' => 55]],
            ['node_id' => 'action_2',  'action_type' => 'notify',                 'status' => 'completed', 'offset_ms' => 4040,  'duration_ms' => 600,  'output' => ['recipients' => 2]],
        ]);

        // Small order → local allocate
        $runSmall = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'completed',
            'triggered_by'           => $this->secondaryUserId,
            'trigger_type'           => 'event',
            'trigger_payload'        => ['order_id' => 2002, 'quantity' => 10],
            'started_at'             => now()->subHours(7),
            'completed_at'           => now()->subHours(7)->addSeconds(3),
            'context'                => ['order_id' => 2002, 'allocated_locally' => true],
            'idempotency_key'        => 'seed-wf7-run-small',
        ]);
        $this->seedSteps($runSmall, [
            ['node_id' => 'trigger_1', 'action_type' => 'order_created',       'status' => 'completed', 'offset_ms' => 0,    'duration_ms' => 25],
            ['node_id' => 'cond_1',    'action_type' => 'quantity_threshold',  'status' => 'completed', 'offset_ms' => 25,   'duration_ms' => 10,   'output' => ['result' => false, 'branch' => 'false']],
            ['node_id' => 'action_3',  'action_type' => 'auto_allocate_order', 'status' => 'completed', 'offset_ms' => 35,   'duration_ms' => 2000, 'output' => ['allocated_batches' => 1, 'total_qty' => 10]],
            ['node_id' => 'action_4',  'action_type' => 'log_audit_event',     'status' => 'completed', 'offset_ms' => 2035, 'duration_ms' => 25,   'output' => ['logged' => true]],
        ]);

        // Failed large order → dead-letter
        $runFail = WorkflowRun::create([
            'workflow_definition_id' => $wf->id,
            'workflow_version_id'    => $v->id,
            'status'                 => 'failed',
            'triggered_by'           => $this->primaryUserId,
            'trigger_type'           => 'event',
            'trigger_payload'        => ['order_id' => 2003, 'quantity' => 500],
            'started_at'             => now()->subDays(2),
            'completed_at'           => now()->subDays(2)->addSeconds(15),
            'error_message'          => 'Dead-lettered after 3 retries. Last error: Insufficient stock across all branches for qty 500',
            'context'                => ['order_id' => 2003],
            'retry_attempt'          => 3,
            'max_retries'            => 3,
            'is_dead_letter'         => true,
            'idempotency_key'        => 'seed-wf7-run-deadletter',
        ]);
        $this->seedSteps($runFail, [
            ['node_id' => 'trigger_1', 'action_type' => 'order_created',           'status' => 'completed', 'offset_ms' => 0,   'duration_ms' => 30],
            ['node_id' => 'cond_1',    'action_type' => 'quantity_threshold',      'status' => 'completed', 'offset_ms' => 30,  'duration_ms' => 10, 'output' => ['result' => true, 'branch' => 'true']],
            ['node_id' => 'action_1',  'action_type' => 'create_transfer_request', 'status' => 'failed',    'offset_ms' => 40,  'duration_ms' => 12000, 'error' => 'Insufficient stock across all branches for qty 500'],
        ]);
    }

    /**
     * Seed a history of scheduled runs over $days past days (alternating success/fail).
     */
    private function seedScheduledRunHistory(WorkflowDefinition $wf, WorkflowVersion $v, int $days): void
    {
        for ($d = $days; $d >= 1; $d--) {
            $isSuccess = $d % 3 !== 0; // every 3rd day fails
            $startTime = now()->subDays($d)->setTime(8, 0, 0);

            $run = WorkflowRun::create([
                'workflow_definition_id' => $wf->id,
                'workflow_version_id'    => $v->id,
                'status'                 => $isSuccess ? 'completed' : 'failed',
                'triggered_by'           => null,
                'trigger_type'           => 'scheduled',
                'trigger_payload'        => ['scheduled_date' => $startTime->toDateString()],
                'started_at'             => $startTime,
                'completed_at'           => $startTime->copy()->addSeconds($isSuccess ? 5 : 3),
                'error_message'          => $isSuccess ? null : 'Report generation timed out after 3s',
                'context'                => $isSuccess
                    ? ['report_generated' => true, 'notification_sent' => true]
                    : ['report_generated' => false],
                'idempotency_key'        => 'seed-' . Str::slug($wf->name) . '-sched-' . $startTime->toDateString(),
            ]);

            // Minimal steps
            WorkflowRunStep::create([
                'workflow_run_id' => $run->id,
                'node_id'        => 'trigger_1',
                'action_type'    => 'daily_schedule',
                'status'         => 'completed',
                'started_at'     => $run->started_at,
                'completed_at'   => $run->started_at->copy()->addMilliseconds(30),
            ]);

            if ($isSuccess) {
                WorkflowRunStep::create([
                    'workflow_run_id' => $run->id,
                    'node_id'        => 'action_1',
                    'action_type'    => 'generate_report',
                    'status'         => 'completed',
                    'started_at'     => $run->started_at->copy()->addMilliseconds(30),
                    'completed_at'   => $run->started_at->copy()->addSeconds(4),
                    'output_snapshot' => ['report_path' => 'reports/' . Str::slug($wf->name) . '-' . $startTime->toDateString() . '.xlsx'],
                ]);
            } else {
                WorkflowRunStep::create([
                    'workflow_run_id' => $run->id,
                    'node_id'        => 'action_1',
                    'action_type'    => 'generate_report',
                    'status'         => 'failed',
                    'error_message'  => 'Report generation timed out after 3s',
                    'started_at'     => $run->started_at->copy()->addMilliseconds(30),
                    'completed_at'   => $run->started_at->copy()->addSeconds(3),
                ]);
            }
        }
    }

    // ==============================================================
    //  Helpers
    // ==============================================================

    private function buildNodes(WorkflowVersion $version, array $nodes): void
    {
        $version->nodes()->createMany($nodes);
    }

    private function buildEdges(WorkflowVersion $version, array $edges): void
    {
        $version->edges()->createMany($edges);
    }

    /**
     * Seed run steps from a compact definition array.
     *
     * Each item: [node_id, action_type, status, offset_ms, duration_ms, ?output, ?error]
     */
    private function seedSteps(WorkflowRun $run, array $stepDefs): void
    {
        foreach ($stepDefs as $def) {
            $startedAt = $run->started_at->copy()->addMilliseconds($def['offset_ms']);

            WorkflowRunStep::create([
                'workflow_run_id'  => $run->id,
                'node_id'         => $def['node_id'],
                'action_type'     => $def['action_type'],
                'status'          => $def['status'],
                'started_at'      => $startedAt,
                'completed_at'    => $def['duration_ms'] !== null
                    ? $startedAt->copy()->addMilliseconds($def['duration_ms'])
                    : null,
                'output_snapshot'  => $def['output'] ?? null,
                'error_message'   => $def['error'] ?? null,
            ]);
        }
    }

    /**
     * Seed per-workflow permissions for primary and secondary users.
     *
     * @param  string[]  $primaryAbilities   Abilities for the primary user
     * @param  string[]  $secondaryAbilities Abilities for the secondary user
     */
    private function seedPermissions(WorkflowDefinition $wf, array $primaryAbilities, array $secondaryAbilities): void
    {
        foreach ($primaryAbilities as $ability) {
            WorkflowPermission::firstOrCreate([
                'workflow_definition_id' => $wf->id,
                'user_id'               => $this->primaryUserId,
                'permission'            => $ability,
            ]);
        }

        foreach ($secondaryAbilities as $ability) {
            WorkflowPermission::firstOrCreate([
                'workflow_definition_id' => $wf->id,
                'user_id'               => $this->secondaryUserId,
                'permission'            => $ability,
            ]);
        }
    }
}
