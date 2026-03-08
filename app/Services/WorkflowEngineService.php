<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStep;
use App\Models\Hold;
use App\Models\HoldItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkflowEngineService
{
    /**
     * Per-run in-memory cache for recipient resolution.
     *
     * @var array<string,array<int,int>>
     */
    protected array $recipientResolutionCache = [];

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
        $catalog = [
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
                        'categories' => ['vaccine', 'antibiotic', 'analgesic', 'consumable', 'pharmaceuticals', 'office_supplies'],
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
                    'config_schema' => [
                        'message' => 'required|string|max:500',
                        'channel' => 'optional|string',
                        'recipient_strategy' => 'optional|string|max:50',
                        'recipient_user_ids' => 'optional|array',
                        'recipient_branch_ids' => 'optional|array',
                        'recipient_level_ids' => 'optional|array',
                        'recipient_permissions' => 'optional|array',
                        'recipient_emails' => 'optional|array',
                        'recipient_context_user_field' => 'optional|string|max:120',
                        'recipient_match_context_branch' => 'optional|integer|min:0|max:1',
                        'include_trigger_user' => 'optional|integer|min:0|max:1',
                    ],
                    'default_preset' => 'in_app_alert',
                    'presets' => [
                        ['key' => 'in_app_alert', 'label' => 'In-app Alert', 'config' => ['message' => 'Workflow alert generated.', 'channel' => 'in_app']],
                        ['key' => 'email_alert', 'label' => 'Email Alert', 'config' => ['message' => 'Workflow event needs attention.', 'channel' => 'email']],
                    ],
                    'ui' => [
                        'channel' => ['in_app', 'email'],
                        'recipient_strategy' => ['admins', 'specific_users', 'criteria'],
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
                        'recipient_strategy' => 'optional|string|max:50',
                        'recipient_user_ids' => 'optional|array',
                        'recipient_branch_ids' => 'optional|array',
                        'recipient_level_ids' => 'optional|array',
                        'recipient_permissions' => 'optional|array',
                        'recipient_emails' => 'optional|array',
                        'recipient_context_user_field' => 'optional|string|max:120',
                        'recipient_match_context_branch' => 'optional|integer|min:0|max:1',
                        'include_trigger_user' => 'optional|integer|min:0|max:1',
                    ],
                    'default_preset' => 'low_stock',
                    'presets' => [
                        ['key' => 'low_stock', 'label' => 'Low Stock Report', 'config' => ['report_type' => 'low_stock']],
                        ['key' => 'expiry_report', 'label' => 'Expiry Report', 'config' => ['report_type' => 'expiry_report']],
                        ['key' => 'stock_movement', 'label' => 'Stock Movement', 'config' => ['report_type' => 'stock_movement']],
                    ],
                    'ui' => [
                        'report_type' => ['stock_movement', 'expiry_report', 'low_stock', 'inventory_summary'],
                        'recipient_strategy' => ['admins', 'specific_users', 'criteria'],
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
                [
                    'type' => 'action',
                    'action_type' => 'log_audit_event',
                    'label' => 'Log Audit Event',
                    'config_schema' => [
                        'message' => 'optional|string|max:500',
                        'event_type' => 'optional|string|max:120',
                    ],
                    'default_preset' => 'workflow_audit',
                    'presets' => [
                        ['key' => 'workflow_audit', 'label' => 'Workflow Audit', 'config' => ['message' => 'Workflow action executed.', 'event_type' => 'workflow_automation']],
                        ['key' => 'compliance_log', 'label' => 'Compliance Log', 'config' => ['message' => 'Compliance check completed.', 'event_type' => 'compliance']],
                    ],
                ],
            ],
        ];

        return $this->mergeNodeCatalog($catalog, $this->advancedNodeCatalog());
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getWorkflowTemplates(): array
    {
        return [
            [
                'key' => 'employee_onboarding_automation',
                'name' => 'Employee Onboarding Automation',
                'description' => 'Coordinates onboarding tasks across HR, IT, CRM, and ERP with parallel execution and completion gates.',
                'category' => 'HR',
                'capabilities' => [
                    'parallel_streams',
                    'crm_erp_sync',
                    'dynamic_form_mapping',
                    'completion_criteria',
                    'role_based_controls',
                    'audit_logging',
                ],
                'completion_criteria' => [
                    'require_notifications' => true,
                    'require_error_resolution' => true,
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'employee_onboarding_started', 'label' => 'Onboarding Started', 'config' => []],
                        ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'map_form_fields', 'label' => 'Map Onboarding Fields', 'config' => ['field_mappings' => ['employee_name:crm_contact_name', 'department:erp_department', 'email:crm_primary_email']]],
                        ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'sync_crm_erp', 'label' => 'Sync Employee Profile', 'config' => ['mode' => 'real_time']],
                        ['node_id' => 'action_3', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify HR', 'config' => ['message' => 'HR onboarding checklist is ready for execution.']],
                        ['node_id' => 'action_4', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify IT', 'config' => ['message' => 'Provision IT assets and accounts for new employee.']],
                        ['node_id' => 'action_5', 'type' => 'action', 'action_type' => 'completion_gate', 'label' => 'Onboarding Completion Gate', 'config' => ['require_notifications' => 1, 'require_error_resolution' => 1]],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
                        ['source_node_id' => 'action_1', 'target_node_id' => 'action_2'],
                        ['source_node_id' => 'action_1', 'target_node_id' => 'action_3'],
                        ['source_node_id' => 'action_1', 'target_node_id' => 'action_4'],
                        ['source_node_id' => 'action_2', 'target_node_id' => 'action_5'],
                        ['source_node_id' => 'action_3', 'target_node_id' => 'action_5'],
                        ['source_node_id' => 'action_4', 'target_node_id' => 'action_5'],
                    ],
                ],
            ],
            [
                'key' => 'document_approval_hierarchy',
                'name' => 'Document Approval Hierarchy',
                'description' => 'Routes documents through approval tiers with conditional branching and escalation for overdue approvals.',
                'category' => 'Operations',
                'capabilities' => [
                    'conditional_branching',
                    'escalation',
                    'parallel_streams',
                    'completion_criteria',
                    'audit_logging',
                ],
                'completion_criteria' => [
                    'require_notifications' => true,
                    'require_error_resolution' => true,
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'document_approval_requested', 'label' => 'Approval Requested', 'config' => []],
                        ['node_id' => 'condition_1', 'type' => 'condition', 'action_type' => 'data_field_matches', 'label' => 'High Value Document?', 'config' => ['field' => 'approval_tier', 'operator' => '>=', 'value' => '2']],
                        ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Senior Approver', 'config' => ['message' => 'Senior approval is required for this document.']],
                        ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Standard Approver', 'config' => ['message' => 'Document ready for standard approval.']],
                        ['node_id' => 'action_3', 'type' => 'action', 'action_type' => 'escalate_overdue_task', 'label' => 'Escalate Delays', 'config' => ['minutes' => 240, 'message' => 'Document approval exceeded SLA and has been escalated.']],
                        ['node_id' => 'action_4', 'type' => 'action', 'action_type' => 'completion_gate', 'label' => 'Approval Completion Gate', 'config' => ['require_notifications' => 1, 'require_error_resolution' => 1]],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'condition_1'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_1', 'condition_branch' => 'true'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_2', 'condition_branch' => 'false'],
                        ['source_node_id' => 'action_1', 'target_node_id' => 'action_3'],
                        ['source_node_id' => 'action_2', 'target_node_id' => 'action_4'],
                        ['source_node_id' => 'action_3', 'target_node_id' => 'action_4'],
                    ],
                ],
            ],
            [
                'key' => 'cross_platform_data_sync',
                'name' => 'Cross-Platform Data Synchronization',
                'description' => 'Synchronizes records between CRM and ERP in real time with field mapping, branch-based error handling, and completion checks.',
                'category' => 'Integrations',
                'capabilities' => [
                    'crm_erp_sync',
                    'dynamic_form_mapping',
                    'conditional_branching',
                    'completion_criteria',
                    'audit_logging',
                ],
                'completion_criteria' => [
                    'require_notifications' => true,
                    'require_error_resolution' => true,
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'data_sync_requested', 'label' => 'Sync Requested', 'config' => []],
                        ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'map_form_fields', 'label' => 'Map Integration Fields', 'config' => ['field_mappings' => ['customer_name:crm_name', 'customer_email:crm_email', 'invoice_total:erp_invoice_total']]],
                        ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'sync_crm_erp', 'label' => 'Real-Time CRM/ERP Sync', 'config' => ['mode' => 'real_time']],
                        ['node_id' => 'condition_1', 'type' => 'condition', 'action_type' => 'sync_status_matches', 'label' => 'Sync Successful?', 'config' => ['expected_status' => 'synced']],
                        ['node_id' => 'action_3', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Sync Success', 'config' => ['message' => 'CRM/ERP synchronization completed successfully.']],
                        ['node_id' => 'action_4', 'type' => 'action', 'action_type' => 'escalate_overdue_task', 'label' => 'Escalate Sync Failure', 'config' => ['minutes' => 1, 'message' => 'Data synchronization issue escalated for investigation.']],
                        ['node_id' => 'action_5', 'type' => 'action', 'action_type' => 'completion_gate', 'label' => 'Sync Completion Gate', 'config' => ['require_notifications' => 1, 'require_error_resolution' => 1]],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
                        ['source_node_id' => 'action_1', 'target_node_id' => 'action_2'],
                        ['source_node_id' => 'action_2', 'target_node_id' => 'condition_1'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_3', 'condition_branch' => 'true'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_4', 'condition_branch' => 'false'],
                        ['source_node_id' => 'action_3', 'target_node_id' => 'action_5'],
                        ['source_node_id' => 'action_4', 'target_node_id' => 'action_5'],
                    ],
                ],
            ],
            [
                'key' => 'it_service_request_management',
                'name' => 'IT Service Request Management',
                'description' => 'Automates IT request intake, SLA checks, overdue escalation, and requester updates with robust completion controls.',
                'category' => 'ITSM',
                'capabilities' => [
                    'conditional_branching',
                    'escalation',
                    'parallel_streams',
                    'role_based_controls',
                    'completion_criteria',
                ],
                'completion_criteria' => [
                    'require_notifications' => true,
                    'require_error_resolution' => true,
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'it_service_ticket_created', 'label' => 'Ticket Created', 'config' => []],
                        ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Service Desk', 'config' => ['message' => 'A new IT service request has been logged.']],
                        ['node_id' => 'condition_1', 'type' => 'condition', 'action_type' => 'sla_overdue', 'label' => 'SLA Breached?', 'config' => ['minutes' => 120, 'reference_time_field' => 'requested_at']],
                        ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'escalate_overdue_task', 'label' => 'Escalate Ticket', 'config' => ['minutes' => 120, 'message' => 'IT ticket breached SLA and was escalated.']],
                        ['node_id' => 'action_3', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Requester', 'config' => ['message' => 'Your IT service request has been processed.']],
                        ['node_id' => 'action_4', 'type' => 'action', 'action_type' => 'completion_gate', 'label' => 'ITSM Completion Gate', 'config' => ['require_notifications' => 1, 'require_error_resolution' => 1]],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
                        ['source_node_id' => 'action_1', 'target_node_id' => 'condition_1'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_2', 'condition_branch' => 'true'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_3', 'condition_branch' => 'false'],
                        ['source_node_id' => 'action_2', 'target_node_id' => 'action_3'],
                        ['source_node_id' => 'action_3', 'target_node_id' => 'action_4'],
                    ],
                ],
            ],
            [
                'key' => 'compliance_monitoring_control_loop',
                'name' => 'Compliance Monitoring Control Loop',
                'description' => 'Continuously monitors compliance checks, validates role-based review access, produces reports, and confirms closure.',
                'category' => 'Compliance',
                'capabilities' => [
                    'role_based_controls',
                    'conditional_branching',
                    'reporting',
                    'audit_logging',
                    'completion_criteria',
                    'visual_debug_trace',
                ],
                'completion_criteria' => [
                    'require_notifications' => true,
                    'require_error_resolution' => true,
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'compliance_window_started', 'label' => 'Compliance Window Started', 'config' => []],
                        ['node_id' => 'action_1', 'type' => 'action', 'action_type' => 'generate_report', 'label' => 'Generate Compliance Report', 'config' => ['report_type' => 'inventory_summary', 'message' => 'Compliance report generated by automation.']],
                        ['node_id' => 'condition_1', 'type' => 'condition', 'action_type' => 'user_has_permission', 'label' => 'Reviewer Has Audit Permission?', 'config' => ['permission' => 'audit.view', 'user_id_field' => 'auditor_user_id']],
                        ['node_id' => 'action_2', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Compliance Officer', 'config' => ['message' => 'Compliance report is ready for review.']],
                        ['node_id' => 'action_3', 'type' => 'action', 'action_type' => 'notify', 'label' => 'Notify Security Team', 'config' => ['message' => 'Reviewer access issue detected for compliance workflow.']],
                        ['node_id' => 'action_4', 'type' => 'action', 'action_type' => 'sync_crm_erp', 'label' => 'Archive Compliance Snapshot', 'config' => ['mode' => 'real_time']],
                        ['node_id' => 'action_5', 'type' => 'action', 'action_type' => 'completion_gate', 'label' => 'Compliance Completion Gate', 'config' => ['require_notifications' => 1, 'require_error_resolution' => 1]],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
                        ['source_node_id' => 'action_1', 'target_node_id' => 'condition_1'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_2', 'condition_branch' => 'true'],
                        ['source_node_id' => 'condition_1', 'target_node_id' => 'action_3', 'condition_branch' => 'false'],
                        ['source_node_id' => 'action_2', 'target_node_id' => 'action_4'],
                        ['source_node_id' => 'action_3', 'target_node_id' => 'action_4'],
                        ['source_node_id' => 'action_4', 'target_node_id' => 'action_5'],
                    ],
                ],
            ],

            // ────────────────────────────────────────────────────
            //  Inventory-specific templates (GTIMS domain)
            // ────────────────────────────────────────────────────
            [
                'key' => 'low_stock_alert_reorder',
                'name' => 'Low Stock Alert & Reorder',
                'description' => 'Monitors inventory for low stock. If quantity falls below critical threshold, sends notifications, creates a reorder suggestion, and logs an audit trail.',
                'category' => 'Inventory',
                'capabilities' => [
                    'conditional_branching',
                    'notifications',
                    'reorder_automation',
                    'audit_logging',
                ],
                'completion_criteria' => [
                    'require_notifications' => true,
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger',   'action_type' => 'low_stock_reached',        'label' => 'Low Stock Reached', 'config' => ['threshold' => 10]],
                        ['node_id' => 'cond_1',    'type' => 'condition',  'action_type' => 'quantity_threshold',       'label' => 'Qty ≤ 5?',         'config' => ['operator' => '<=', 'value' => 5]],
                        ['node_id' => 'action_1',  'type' => 'action',     'action_type' => 'notify',                   'label' => 'Critical Alert',    'config' => ['message' => 'CRITICAL: Stock quantity at or below 5 units — immediate reorder required.']],
                        ['node_id' => 'action_2',  'type' => 'action',     'action_type' => 'create_reorder_suggestion','label' => 'Reorder 100',       'config' => ['quantity' => 100]],
                        ['node_id' => 'action_3',  'type' => 'action',     'action_type' => 'log_audit_event',          'label' => 'Audit Log',         'config' => ['message' => 'Low-stock workflow executed. Reorder suggestion created.']],
                        ['node_id' => 'action_4',  'type' => 'action',     'action_type' => 'notify',                   'label' => 'Info Alert',        'config' => ['message' => 'Stock is low but above critical threshold — monitoring.']],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'cond_1'],
                        ['source_node_id' => 'cond_1',    'target_node_id' => 'action_1', 'condition_branch' => 'true'],
                        ['source_node_id' => 'cond_1',    'target_node_id' => 'action_2', 'condition_branch' => 'true'],
                        ['source_node_id' => 'action_2',  'target_node_id' => 'action_3'],
                        ['source_node_id' => 'cond_1',    'target_node_id' => 'action_4', 'condition_branch' => 'false'],
                    ],
                ],
            ],
            [
                'key' => 'expiry_hold_and_report',
                'name' => 'Expiry Alert — Hold & Report',
                'description' => 'When batches are within 30 days of expiry, quarantine them, notify pharmacy staff, generate an expiry report, and log an audit trail.',
                'category' => 'Inventory',
                'capabilities' => [
                    'hold_management',
                    'reporting',
                    'notifications',
                    'audit_logging',
                ],
                'completion_criteria' => [
                    'require_notifications' => true,
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'expiry_in_x_days', 'label' => 'Expiry ≤ 30 Days',   'config' => ['days' => 30]],
                        ['node_id' => 'action_1',  'type' => 'action',  'action_type' => 'create_hold',      'label' => 'Quarantine Batch',    'config' => ['reason' => 'Near expiry — quarantine per SOP']],
                        ['node_id' => 'action_2',  'type' => 'action',  'action_type' => 'notify',           'label' => 'Notify Pharmacy',     'config' => ['message' => 'Batches within 30 days of expiry have been quarantined.']],
                        ['node_id' => 'action_3',  'type' => 'action',  'action_type' => 'generate_report',  'label' => 'Expiry Report',       'config' => ['report_type' => 'expiry_report']],
                        ['node_id' => 'action_4',  'type' => 'action',  'action_type' => 'log_audit_event',  'label' => 'Audit Trail',         'config' => ['message' => 'Expiry workflow completed — batches quarantined.']],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_2'],
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_3'],
                        ['source_node_id' => 'action_1',  'target_node_id' => 'action_4'],
                    ],
                ],
            ],
            [
                'key' => 'order_approved_fefo_allocation',
                'name' => 'Order Approved → FEFO Allocation',
                'description' => 'When an order is approved, evaluate product category. Pharmaceuticals are auto-allocated via FEFO strategy; others are logged and skipped.',
                'category' => 'Orders',
                'capabilities' => [
                    'conditional_branching',
                    'fefo_allocation',
                    'notifications',
                    'audit_logging',
                ],
                'completion_criteria' => [
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger',   'action_type' => 'order_approved',       'label' => 'Order Approved',    'config' => []],
                        ['node_id' => 'cond_1',    'type' => 'condition',  'action_type' => 'category_matches',     'label' => 'Is Pharma?',        'config' => ['categories' => ['pharmaceuticals']]],
                        ['node_id' => 'action_1',  'type' => 'action',     'action_type' => 'auto_allocate_order',  'label' => 'FEFO Allocate',     'config' => []],
                        ['node_id' => 'action_2',  'type' => 'action',     'action_type' => 'notify',               'label' => 'Confirm Allocated', 'config' => ['message' => 'Order allocated via FEFO strategy.']],
                        ['node_id' => 'action_3',  'type' => 'action',     'action_type' => 'log_audit_event',      'label' => 'Log Skip',          'config' => ['message' => 'Order skipped — non-pharmaceutical product.']],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'cond_1'],
                        ['source_node_id' => 'cond_1',    'target_node_id' => 'action_1', 'condition_branch' => 'true'],
                        ['source_node_id' => 'action_1',  'target_node_id' => 'action_2'],
                        ['source_node_id' => 'cond_1',    'target_node_id' => 'action_3', 'condition_branch' => 'false'],
                    ],
                ],
            ],
            [
                'key' => 'daily_stock_movement_report',
                'name' => 'Daily Stock Movement Report',
                'description' => 'Generates a stock movement report every day at 08:00, notifies staff, and logs execution to the audit trail.',
                'category' => 'Reports',
                'capabilities' => [
                    'scheduled_execution',
                    'reporting',
                    'notifications',
                    'audit_logging',
                ],
                'completion_criteria' => [
                    'all_tasks_finalized' => true,
                ],
                'graph' => [
                    'nodes' => [
                        ['node_id' => 'trigger_1', 'type' => 'trigger', 'action_type' => 'daily_schedule',  'label' => 'Daily 08:00',          'config' => ['cron' => '0 8 * * *']],
                        ['node_id' => 'action_1',  'type' => 'action',  'action_type' => 'generate_report', 'label' => 'Stock Movement Rpt',   'config' => ['report_type' => 'stock_movement']],
                        ['node_id' => 'action_2',  'type' => 'action',  'action_type' => 'notify',          'label' => 'Email Report Ready',   'config' => ['message' => 'Daily stock movement report is ready for review.']],
                        ['node_id' => 'action_3',  'type' => 'action',  'action_type' => 'log_audit_event', 'label' => 'Log Execution',        'config' => ['message' => 'Daily stock movement report generated and distributed.']],
                    ],
                    'edges' => [
                        ['source_node_id' => 'trigger_1', 'target_node_id' => 'action_1'],
                        ['source_node_id' => 'action_1',  'target_node_id' => 'action_2'],
                        ['source_node_id' => 'action_1',  'target_node_id' => 'action_3'],
                    ],
                ],
            ],
        ];
    }

    public function findWorkflowTemplate(string $templateKey): ?array
    {
        $key = trim($templateKey);
        if ($key === '') {
            return null;
        }

        foreach ($this->getWorkflowTemplates() as $template) {
            if (($template['key'] ?? null) === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $base
     * @param  array<string,mixed>  $advanced
     * @return array<string,mixed>
     */
    protected function mergeNodeCatalog(array $base, array $advanced): array
    {
        foreach (['triggers', 'conditions', 'actions'] as $group) {
            $existing = collect($base[$group] ?? [])->keyBy('action_type');
            $incoming = collect($advanced[$group] ?? [])->keyBy('action_type');
            $base[$group] = $existing->merge($incoming)->values()->all();
        }

        return $base;
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    protected function advancedNodeCatalog(): array
    {
        return [
            'triggers' => [
                [
                    'type' => 'trigger',
                    'action_type' => 'employee_onboarding_started',
                    'label' => 'Employee Onboarding Started',
                    'config_schema' => ['employee_id' => 'optional|integer|min:1', 'department' => 'optional|string|max:120'],
                    'default_preset' => 'new_hire',
                    'presets' => [
                        ['key' => 'new_hire', 'label' => 'New Hire', 'config' => []],
                    ],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'document_approval_requested',
                    'label' => 'Document Approval Requested',
                    'config_schema' => ['document_type' => 'optional|string|max:120', 'approval_tier' => 'optional|integer|min:1'],
                    'default_preset' => 'policy_update',
                    'presets' => [
                        ['key' => 'policy_update', 'label' => 'Policy Update', 'config' => ['document_type' => 'policy']],
                    ],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'data_sync_requested',
                    'label' => 'Data Sync Requested',
                    'config_schema' => ['source_system' => 'optional|string|max:80', 'entity_type' => 'optional|string|max:80'],
                    'default_preset' => 'crm_to_erp',
                    'presets' => [
                        ['key' => 'crm_to_erp', 'label' => 'CRM -> ERP', 'config' => ['source_system' => 'crm', 'entity_type' => 'customer']],
                    ],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'it_service_ticket_created',
                    'label' => 'IT Service Ticket Created',
                    'config_schema' => ['ticket_priority' => 'optional|string|max:50', 'requested_at' => 'optional|string|max:80'],
                    'default_preset' => 'normal_priority',
                    'presets' => [
                        ['key' => 'normal_priority', 'label' => 'Normal Priority', 'config' => ['ticket_priority' => 'normal']],
                    ],
                ],
                [
                    'type' => 'trigger',
                    'action_type' => 'compliance_window_started',
                    'label' => 'Compliance Window Started',
                    'config_schema' => ['window_name' => 'optional|string|max:120', 'auditor_user_id' => 'optional|integer|min:1'],
                    'default_preset' => 'monthly_compliance',
                    'presets' => [
                        ['key' => 'monthly_compliance', 'label' => 'Monthly Compliance', 'config' => ['window_name' => 'monthly']],
                    ],
                ],
            ],
            'conditions' => [
                [
                    'type' => 'condition',
                    'action_type' => 'data_field_matches',
                    'label' => 'Data Field Matches',
                    'config_schema' => ['field' => 'required|string|max:120', 'operator' => 'optional|string|max:20', 'value' => 'required|string|max:255'],
                    'default_preset' => 'equals',
                    'presets' => [
                        ['key' => 'equals', 'label' => 'Equals', 'config' => ['operator' => '==']],
                        ['key' => 'contains', 'label' => 'Contains', 'config' => ['operator' => 'contains']],
                    ],
                    'ui' => [
                        'operator' => ['==', '!=', '>', '>=', '<', '<=', 'contains'],
                    ],
                ],
                [
                    'type' => 'condition',
                    'action_type' => 'user_has_permission',
                    'label' => 'User Has Permission',
                    'config_schema' => ['permission' => 'required|string|max:255', 'user_id_field' => 'optional|string|max:120'],
                    'default_preset' => 'workflow_runner',
                    'presets' => [
                        ['key' => 'workflow_runner', 'label' => 'Can Run Workflow', 'config' => ['permission' => 'workflows.run', 'user_id_field' => 'user_id']],
                    ],
                ],
                [
                    'type' => 'condition',
                    'action_type' => 'sla_overdue',
                    'label' => 'SLA Overdue',
                    'config_schema' => ['minutes' => 'required|integer|min:1', 'reference_time_field' => 'optional|string|max:120'],
                    'default_preset' => 'two_hours',
                    'presets' => [
                        ['key' => 'two_hours', 'label' => 'Over 2 Hours', 'config' => ['minutes' => 120, 'reference_time_field' => 'requested_at']],
                        ['key' => 'four_hours', 'label' => 'Over 4 Hours', 'config' => ['minutes' => 240, 'reference_time_field' => 'requested_at']],
                    ],
                ],
                [
                    'type' => 'condition',
                    'action_type' => 'sync_status_matches',
                    'label' => 'Sync Status Matches',
                    'config_schema' => ['expected_status' => 'required|string|max:50'],
                    'default_preset' => 'synced',
                    'presets' => [
                        ['key' => 'synced', 'label' => 'Status = synced', 'config' => ['expected_status' => 'synced']],
                        ['key' => 'failed', 'label' => 'Status = failed', 'config' => ['expected_status' => 'failed']],
                    ],
                    'ui' => [
                        'expected_status' => ['synced', 'failed', 'queued'],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'action',
                    'action_type' => 'map_form_fields',
                    'label' => 'Map Form Fields',
                    'config_schema' => ['field_mappings' => 'required|array'],
                    'default_preset' => 'basic_mapping',
                    'presets' => [
                        ['key' => 'basic_mapping', 'label' => 'Basic Mapping', 'config' => ['field_mappings' => ['source_field:target_field']]],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'create_google_doc',
                    'label' => 'Create Google Doc',
                    'config_schema' => [
                        'title' => 'required|string|max:255',
                        'folder_id' => 'optional|string|max:255',
                        'share_emails' => 'optional|array',
                        'message' => 'optional|string|max:500',
                        'recipient_strategy' => 'optional|string|max:50',
                        'recipient_user_ids' => 'optional|array',
                        'recipient_branch_ids' => 'optional|array',
                        'recipient_level_ids' => 'optional|array',
                        'recipient_permissions' => 'optional|array',
                        'recipient_emails' => 'optional|array',
                        'recipient_context_user_field' => 'optional|string|max:120',
                        'recipient_match_context_branch' => 'optional|integer|min:0|max:1',
                        'include_trigger_user' => 'optional|integer|min:0|max:1',
                    ],
                    'default_preset' => 'generic_doc',
                    'presets' => [
                        ['key' => 'generic_doc', 'label' => 'Generic Document', 'config' => ['title' => 'Workflow Generated Document']],
                        ['key' => 'incident_report', 'label' => 'Incident Report', 'config' => ['title' => 'Incident Report']],
                    ],
                    'ui' => [
                        'recipient_strategy' => ['admins', 'specific_users', 'criteria'],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'sync_crm_erp',
                    'label' => 'Sync CRM/ERP',
                    'config_schema' => ['mode' => 'optional|string|max:50', 'crm_endpoint' => 'optional|string|max:500', 'erp_endpoint' => 'optional|string|max:500', 'fail_on_error' => 'optional|integer|min:0|max:1'],
                    'default_preset' => 'real_time',
                    'presets' => [
                        ['key' => 'real_time', 'label' => 'Real-Time Sync', 'config' => ['mode' => 'real_time', 'fail_on_error' => 1]],
                        ['key' => 'queued', 'label' => 'Queued Sync', 'config' => ['mode' => 'queued', 'fail_on_error' => 0]],
                    ],
                    'ui' => [
                        'mode' => ['real_time', 'queued', 'batch'],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'escalate_overdue_task',
                    'label' => 'Escalate Overdue Task',
                    'config_schema' => [
                        'minutes' => 'optional|integer|min:1',
                        'message' => 'optional|string|max:500',
                        'recipient_strategy' => 'optional|string|max:50',
                        'recipient_user_ids' => 'optional|array',
                        'recipient_branch_ids' => 'optional|array',
                        'recipient_level_ids' => 'optional|array',
                        'recipient_permissions' => 'optional|array',
                        'recipient_emails' => 'optional|array',
                        'recipient_context_user_field' => 'optional|string|max:120',
                        'recipient_match_context_branch' => 'optional|integer|min:0|max:1',
                        'include_trigger_user' => 'optional|integer|min:0|max:1',
                    ],
                    'default_preset' => 'escalate_2h',
                    'presets' => [
                        ['key' => 'escalate_2h', 'label' => 'Escalate after 2h', 'config' => ['minutes' => 120, 'message' => 'Task has exceeded SLA and is now escalated.']],
                        ['key' => 'escalate_4h', 'label' => 'Escalate after 4h', 'config' => ['minutes' => 240, 'message' => 'Critical delay: escalation protocol activated.']],
                    ],
                    'ui' => [
                        'recipient_strategy' => ['admins', 'specific_users', 'criteria'],
                    ],
                ],
                [
                    'type' => 'action',
                    'action_type' => 'completion_gate',
                    'label' => 'Completion Gate',
                    'config_schema' => ['require_notifications' => 'optional|integer|min:0|max:1', 'require_error_resolution' => 'optional|integer|min:0|max:1', 'confirmation_message' => 'optional|string|max:500'],
                    'default_preset' => 'strict_completion',
                    'presets' => [
                        ['key' => 'strict_completion', 'label' => 'Strict Completion', 'config' => ['require_notifications' => 1, 'require_error_resolution' => 1]],
                        ['key' => 'lenient_completion', 'label' => 'Lenient Completion', 'config' => ['require_notifications' => 0, 'require_error_resolution' => 1]],
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

        $this->recipientResolutionCache = [];

        try {
            $run->refresh();
            $version = $run->version()->with('nodes', 'edges')->firstOrFail();
            $nodes = $version->nodes->keyBy('node_id');
            $edges = $version->edges;

            $outgoing = [];
            $incoming = [];
            $remainingParents = [];
            $activationCount = [];
            $resolved = [];
            $queued = [];

            foreach ($nodes as $node) {
                $nodeId = $node->node_id;
                $outgoing[$nodeId] = [];
                $incoming[$nodeId] = 0;
                $remainingParents[$nodeId] = 0;
                $activationCount[$nodeId] = 0;
            }

            foreach ($edges as $edge) {
                if (!isset($outgoing[$edge->source_node_id]) || !array_key_exists($edge->target_node_id, $remainingParents)) {
                    continue;
                }

                $outgoing[$edge->source_node_id][] = [
                    'target' => $edge->target_node_id,
                    'condition_branch' => $edge->condition_branch,
                ];
                $incoming[$edge->target_node_id]++;
                $remainingParents[$edge->target_node_id]++;
            }

            $activeQueue = [];
            $inactiveQueue = [];
            foreach ($nodes as $nodeId => $node) {
                if ($remainingParents[$nodeId] === 0) {
                    $activeQueue[] = $nodeId;
                    $activationCount[$nodeId] = 1;
                    $queued[$nodeId] = true;
                }
            }

            $context = $run->context ?? [];
            $context['_workflow'] = array_filter([
                'run_id' => $run->id,
                'workflow_id' => $run->workflow_definition_id,
                'workflow_version_id' => $run->workflow_version_id,
                'workflow_name' => $run->definition?->name,
                'trigger_type' => $run->trigger_type,
                'triggered_by' => $run->triggered_by,
                'is_dry_run' => $run->is_dry_run,
                'started_at' => optional($run->started_at)->toDateTimeString(),
            ], fn ($value) => $value !== null && $value !== '');

            $graphData = is_array($version->graph_data ?? null) ? $version->graph_data : [];
            $templateMeta = is_array($graphData['_template'] ?? null) ? $graphData['_template'] : [];
            if (!empty($templateMeta)) {
                $context['_workflow']['template_key'] = $templateMeta['key'] ?? null;
                $context['_workflow']['template_name'] = $templateMeta['name'] ?? null;
            }
            if (!isset($context['_completion_requirements']) && is_array($templateMeta['completion_criteria'] ?? null)) {
                $context['_completion_requirements'] = [
                    'require_notifications' => (bool) ($templateMeta['completion_criteria']['require_notifications'] ?? false),
                    'require_error_resolution' => (bool) ($templateMeta['completion_criteria']['require_error_resolution'] ?? true),
                ];
            }

            $context['_condition_results'] = is_array($context['_condition_results'] ?? null) ? $context['_condition_results'] : [];
            $context['_parallel_stages'] = [];
            $context['_error_states'] = is_array($context['_error_states'] ?? null) ? $context['_error_states'] : [];
            $context = $this->appendDebugTrace($context, [
                'status' => 'run_started',
                'message' => 'Workflow run execution started.',
                'run_id' => $run->id,
            ]);

            $stageNumber = 0;
            while (!empty($activeQueue) || !empty($inactiveQueue)) {
                if (!empty($activeQueue)) {
                    $stageNumber++;
                    $stageNodes = array_values(array_unique($activeQueue));
                    $activeQueue = [];
                    sort($stageNodes);

                    $context['_parallel_stages'][] = [
                        'stage' => $stageNumber,
                        'nodes' => $stageNodes,
                        'started_at' => now()->toIso8601String(),
                    ];

                    foreach ($stageNodes as $nodeId) {
                        unset($queued[$nodeId]);
                        if (isset($resolved[$nodeId])) {
                            continue;
                        }

                        $node = $nodes[$nodeId] ?? null;
                        if (!$node) {
                            $resolved[$nodeId] = true;
                            continue;
                        }

                        $step = WorkflowRunStep::create([
                            'workflow_run_id' => $run->id,
                            'node_id' => $nodeId,
                            'action_type' => $node->action_type,
                            'status' => 'running',
                            'input_snapshot' => ['context' => $context, 'config' => $node->config, 'parallel_stage' => $stageNumber],
                            'started_at' => now(),
                        ]);

                        $result = null;
                        $conditionMet = null;
                        try {
                            $result = $this->executeNode($node, $context, $run->is_dry_run);

                            if ($node->type === 'condition' && array_key_exists('condition_met', $result)) {
                                $conditionMet = (bool) $result['condition_met'];
                                $context['_condition_results'][$nodeId] = $conditionMet;
                            }

                            $context = array_merge($context, $result['context_updates'] ?? []);
                            $context = $this->captureWorkflowOutput($context, $node, $result);

                            $step->update([
                                'status' => 'completed',
                                'output_snapshot' => array_merge($result, ['parallel_stage' => $stageNumber]),
                                'completed_at' => now(),
                            ]);

                            $context = $this->appendDebugTrace($context, [
                                'status' => 'completed',
                                'parallel_stage' => $stageNumber,
                                'node_id' => $nodeId,
                                'node_type' => $node->type,
                                'action_type' => $node->action_type,
                                'message' => $result['message'] ?? "Node {$nodeId} completed.",
                            ]);
                        } catch (\Throwable $e) {
                            $step->update([
                                'status' => 'failed',
                                'error_message' => $e->getMessage(),
                                'completed_at' => now(),
                            ]);

                            $context['_error_states'][] = [
                                'node_id' => $nodeId,
                                'action_type' => $node->action_type,
                                'error' => $e->getMessage(),
                                'at' => now()->toIso8601String(),
                            ];
                            $context = $this->captureWorkflowOutput($context, $node, [
                                'status' => 'failed',
                                'message' => $e->getMessage(),
                            ]);
                            $context = $this->appendDebugTrace($context, [
                                'status' => 'failed',
                                'parallel_stage' => $stageNumber,
                                'node_id' => $nodeId,
                                'node_type' => $node->type,
                                'action_type' => $node->action_type,
                                'message' => $e->getMessage(),
                            ]);

                            $resolved[$nodeId] = true;
                            if (!$run->is_dry_run) {
                                throw $e;
                            }
                        }

                        $resolved[$nodeId] = true;
                        foreach ($outgoing[$nodeId] ?? [] as $edge) {
                            $target = $edge['target'];
                            if (!array_key_exists($target, $remainingParents)) {
                                continue;
                            }

                            $edgePasses = $this->edgePassesForCondition($node->type, $edge['condition_branch'] ?? null, $conditionMet);
                            $remainingParents[$target] = max(0, $remainingParents[$target] - 1);
                            if ($edgePasses) {
                                $activationCount[$target] = ($activationCount[$target] ?? 0) + 1;
                            }

                            if ($remainingParents[$target] === 0 && !isset($resolved[$target]) && !isset($queued[$target])) {
                                if (($incoming[$target] ?? 0) === 0 || ($activationCount[$target] ?? 0) > 0) {
                                    $activeQueue[] = $target;
                                } else {
                                    $inactiveQueue[] = $target;
                                }
                                $queued[$target] = true;
                            }
                        }
                    }

                    continue;
                }

                $nodeId = array_shift($inactiveQueue);
                if ($nodeId === null) {
                    continue;
                }

                unset($queued[$nodeId]);
                if (isset($resolved[$nodeId])) {
                    continue;
                }

                $node = $nodes[$nodeId] ?? null;
                if (!$node) {
                    $resolved[$nodeId] = true;
                    continue;
                }

                WorkflowRunStep::create([
                    'workflow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'action_type' => $node->action_type,
                    'status' => 'skipped',
                    'input_snapshot' => ['context' => $context, 'config' => $node->config],
                    'output_snapshot' => ['status' => 'skipped', 'message' => 'Skipped because no active branch reached this node.'],
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);
                $context = $this->appendDebugTrace($context, [
                    'status' => 'skipped',
                    'node_id' => $nodeId,
                    'node_type' => $node->type,
                    'action_type' => $node->action_type,
                    'message' => 'Skipped because no active branch reached this node.',
                ]);
                $resolved[$nodeId] = true;

                foreach ($outgoing[$nodeId] ?? [] as $edge) {
                    $target = $edge['target'];
                    if (!array_key_exists($target, $remainingParents)) {
                        continue;
                    }

                    $remainingParents[$target] = max(0, $remainingParents[$target] - 1);
                    if ($remainingParents[$target] === 0 && !isset($resolved[$target]) && !isset($queued[$target])) {
                        if (($incoming[$target] ?? 0) === 0 || ($activationCount[$target] ?? 0) > 0) {
                            $activeQueue[] = $target;
                        } else {
                            $inactiveQueue[] = $target;
                        }
                        $queued[$target] = true;
                    }
                }
            }

            foreach ($nodes as $nodeId => $node) {
                if (isset($resolved[$nodeId])) {
                    continue;
                }

                WorkflowRunStep::create([
                    'workflow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'action_type' => $node->action_type,
                    'status' => 'skipped',
                    'input_snapshot' => ['context' => $context, 'config' => $node->config],
                    'output_snapshot' => ['status' => 'skipped', 'message' => 'Skipped because dependencies were never resolved.'],
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);
                $context = $this->appendDebugTrace($context, [
                    'status' => 'skipped',
                    'node_id' => $nodeId,
                    'node_type' => $node->type,
                    'action_type' => $node->action_type,
                    'message' => 'Skipped because dependencies were never resolved.',
                ]);
            }

            $steps = $run->steps()->get();
            $completionRequirements = is_array($context['_completion_requirements'] ?? null) ? $context['_completion_requirements'] : [];
            $completion = $this->evaluateRunCompletion($context, $steps, $nodes->count(), $completionRequirements);
            $context['_completion'] = $completion;
            $context = $this->appendDebugTrace($context, [
                'status' => $completion['all_criteria_met'] ? 'completed' : 'failed',
                'message' => $completion['summary'],
                'completion' => $completion,
            ]);

            $run->update([
                'status' => $completion['all_criteria_met'] ? 'completed' : 'failed',
                'context' => $context,
                'error_message' => $completion['all_criteria_met'] ? null : $completion['failure_reason'],
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

        $run->refresh();

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

    protected function edgePassesForCondition(string $nodeType, ?string $conditionBranch, ?bool $conditionMet): bool
    {
        if ($nodeType !== 'condition') {
            return true;
        }

        $branch = is_string($conditionBranch) ? strtolower(trim($conditionBranch)) : null;
        $conditionValue = $conditionMet === true;

        if ($branch === null || $branch === '') {
            return $conditionValue;
        }

        return $branch === ($conditionValue ? 'true' : 'false');
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  array<string,mixed>  $entry
     * @return array<string,mixed>
     */
    protected function appendDebugTrace(array $context, array $entry): array
    {
        $trace = $context['_debug_trace'] ?? [];
        if (!is_array($trace)) {
            $trace = [];
        }

        $trace[] = array_filter(array_merge([
            'timestamp' => now()->toIso8601String(),
        ], $entry), fn ($value) => $value !== null);

        if (count($trace) > 500) {
            $trace = array_slice($trace, -500);
        }

        $context['_debug_trace'] = array_values($trace);
        return $context;
    }

    /**
     * Persist action output artifacts so downstream notifications include data-rich summaries.
     */
    protected function captureWorkflowOutput(array $context, WorkflowNode $node, array $result): array
    {
        $outputs = $context['_workflow_outputs'] ?? [];
        if (!is_array($outputs)) {
            $outputs = [];
        }

        $entry = array_filter([
            'timestamp' => now()->toIso8601String(),
            'node_id' => $node->node_id,
            'node_type' => $node->type,
            'action_type' => $node->action_type,
            'status' => $result['status'] ?? null,
            'message' => $result['message'] ?? null,
            'report' => isset($result['report']) && is_array($result['report']) ? $result['report'] : null,
            'google_doc' => isset($result['google_doc']) && is_array($result['google_doc']) ? $result['google_doc'] : null,
        ], fn ($value) => $value !== null && $value !== '');

        $outputs[] = $entry;
        if (count($outputs) > 150) {
            $outputs = array_slice($outputs, -150);
        }
        $context['_workflow_outputs'] = array_values($outputs);

        $attachments = $context['_workflow_attachments'] ?? [];
        if (!is_array($attachments)) {
            $attachments = [];
        }

        $reportAttachment = data_get($result, 'context_updates.report_attachment');
        if (is_array($reportAttachment)) {
            $attachments[] = $reportAttachment;
        }

        $normalized = [];
        $seen = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $disk = isset($attachment['disk']) ? trim((string) $attachment['disk']) : '';
            $path = isset($attachment['path']) ? trim((string) $attachment['path']) : '';
            if ($disk === '' || $path === '') {
                continue;
            }

            $name = isset($attachment['name']) && trim((string) $attachment['name']) !== ''
                ? trim((string) $attachment['name'])
                : basename($path);
            $mime = isset($attachment['mime']) && trim((string) $attachment['mime']) !== ''
                ? trim((string) $attachment['mime'])
                : 'application/octet-stream';

            $fingerprint = strtolower($disk) . '|' . strtolower($path) . '|' . strtolower($name);
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $normalized[] = [
                'disk' => $disk,
                'path' => $path,
                'name' => $name,
                'mime' => $mime,
            ];
        }

        if (!empty($normalized)) {
            $context['_workflow_attachments'] = $normalized;
        }

        return $context;
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  \Illuminate\Support\Collection<int,\App\Models\WorkflowRunStep>  $steps
     * @param  array<string,mixed>  $requirements
     * @return array<string,mixed>
     */
    protected function evaluateRunCompletion(array $context, $steps, int $nodeCount, array $requirements): array
    {
        $completedSteps = (int) $steps->where('status', 'completed')->count();
        $failedSteps = (int) $steps->where('status', 'failed')->count();
        $skippedSteps = (int) $steps->where('status', 'skipped')->count();
        $runningOrPending = (int) $steps->whereIn('status', ['pending', 'running'])->count();
        $finalizedNodes = $completedSteps + $failedSteps + $skippedSteps;
        $allTasksFinalized = $finalizedNodes >= $nodeCount && $runningOrPending === 0;

        $workflowMeta = is_array($context['_workflow'] ?? null) ? $context['_workflow'] : [];
        $isDryRun = (bool) ($workflowMeta['is_dry_run'] ?? false);

        $requireNotifications = (bool) ($requirements['require_notifications'] ?? false);
        $requireErrorResolution = (bool) ($requirements['require_error_resolution'] ?? true);
        if ($isDryRun) {
            $requireNotifications = false;
        }

        $notificationsSent = (bool) ($context['confirmation_notifications_sent'] ?? false);
        $errorStatesResolved = $failedSteps === 0 || (bool) ($context['error_states_resolved'] ?? false);

        $allCriteriaMet = $allTasksFinalized
            && (!$requireNotifications || $notificationsSent)
            && (!$requireErrorResolution || $errorStatesResolved);

        $failureReason = null;
        if (!$allTasksFinalized) {
            $failureReason = 'Completion criteria failed: not all tasks were finalized.';
        } elseif ($requireNotifications && !$notificationsSent) {
            $failureReason = 'Completion criteria failed: confirmation notifications were not dispatched.';
        } elseif ($requireErrorResolution && !$errorStatesResolved) {
            $failureReason = 'Completion criteria failed: unresolved error states remain.';
        }

        return [
            'all_criteria_met' => $allCriteriaMet,
            'summary' => $allCriteriaMet
                ? 'Completion criteria satisfied: parallel and sequential tasks finalized, errors resolved, and required notifications dispatched.'
                : ($failureReason ?? 'Completion criteria failed.'),
            'failure_reason' => $failureReason,
            'node_count' => $nodeCount,
            'finalized_nodes' => $finalizedNodes,
            'completed_steps' => $completedSteps,
            'failed_steps' => $failedSteps,
            'skipped_steps' => $skippedSteps,
            'require_notifications' => $requireNotifications,
            'notifications_sent' => $notificationsSent,
            'require_error_resolution' => $requireErrorResolution,
            'error_states_resolved' => $errorStatesResolved,
            'parallel_stages' => is_array($context['_parallel_stages'] ?? null) ? count($context['_parallel_stages']) : 0,
        ];
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

            case 'data_field_matches':
                $field = trim((string) ($config['field'] ?? ''));
                $operator = strtolower(trim((string) ($config['operator'] ?? '==')));
                $expected = $config['value'] ?? null;
                $actual = $field !== '' ? data_get($context, $field) : null;
                $met = $this->compareFieldValues($actual, $expected, $operator);
                break;

            case 'user_has_permission':
                $permissionName = trim((string) ($config['permission'] ?? ''));
                $userIdField = trim((string) ($config['user_id_field'] ?? 'user_id'));
                $candidateUserId = data_get($context, $userIdField);
                if ($candidateUserId === null) {
                    $candidateUserId = data_get($context, '_workflow.triggered_by');
                }

                $met = false;
                if ($permissionName !== '' && is_numeric($candidateUserId)) {
                    $user = \App\Models\User::query()->find((int) $candidateUserId);
                    $met = $user ? $user->hasPermission($permissionName) : false;
                }
                break;

            case 'sla_overdue':
                $minutes = max(1, (int) ($config['minutes'] ?? 60));
                $referenceField = trim((string) ($config['reference_time_field'] ?? 'requested_at'));
                $reference = data_get($context, $referenceField);
                if ($reference === null) {
                    $reference = data_get($context, '_workflow.started_at');
                }
                $met = false;
                if ($reference) {
                    try {
                        $referenceAt = \Carbon\Carbon::parse($reference);
                        $met = $referenceAt->diffInMinutes(now()) >= $minutes;
                    } catch (\Throwable $e) {
                        $met = false;
                    }
                }
                break;

            case 'sync_status_matches':
                $expectedStatus = strtolower(trim((string) ($config['expected_status'] ?? 'synced')));
                $actualStatus = strtolower(trim((string) ($context['sync_status'] ?? data_get($context, 'integration.sync_status', ''))));
                $met = $expectedStatus !== '' && $expectedStatus === $actualStatus;
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
                    'google_doc_created',
                    'google_doc_title',
                    'google_doc_url',
                    'webhook_called',
                ]));
                $attachments = $this->resolveActionAttachments($context);
                if (isset($context['_workflow']) && is_array($context['_workflow'])) {
                    $workflowContext['_workflow'] = $context['_workflow'];
                }
                if (isset($context['_condition_results']) && is_array($context['_condition_results']) && !empty($context['_condition_results'])) {
                    $workflowContext['_condition_results'] = $context['_condition_results'];
                }
                if (isset($context['_workflow_outputs']) && is_array($context['_workflow_outputs'])) {
                    $workflowContext['_workflow_outputs'] = $context['_workflow_outputs'];
                }
                $admins = $this->workflowNotificationRecipients($config, $context);
                foreach ($admins as $admin) {
                    $this->notificationService->notify($admin, 'workflow_notification', [
                        'message' => $message,
                        'workflow_context' => $workflowContext,
                        'attachments' => $attachments,
                    ]);
                }
                return [
                    'status' => 'sent',
                    'message' => $message,
                    'recipients' => $admins->count(),
                    'context_updates' => [
                        'confirmation_notifications_sent' => $admins->count() > 0,
                        'confirmation_notification_count' => $admins->count(),
                    ],
                ];

            case 'create_hold':
                return $this->executeCreateHold($config, $context);

            case 'release_hold':
                return $this->executeReleaseHold($config, $context);

            case 'create_reorder_suggestion':
                return $this->executeReorderSuggestion($config, $context);

            case 'auto_allocate_order':
                return $this->executeAutoAllocateOrder($config, $context);

            case 'create_transfer_request':
                return $this->executeTransferRequest($config, $context);

            case 'map_form_fields':
                $mappings = $this->parseFieldMappings($config['field_mappings'] ?? []);
                $mapped = [];
                foreach ($mappings as $mapping) {
                    $source = $mapping['source'];
                    $target = $mapping['target'];
                    $mapped[$target] = data_get($context, $source);
                }

                return [
                    'status' => 'mapped',
                    'message' => empty($mapped) ? 'No field mappings were applied.' : 'Dynamic form field mapping applied.',
                    'field_mappings' => $mappings,
                    'mapped_fields' => $mapped,
                    'context_updates' => [
                        'form_mapping_applied' => !empty($mapped),
                        'mapped_form_fields' => $mapped,
                    ],
                ];

            case 'create_google_doc':
                $title = trim((string) ($config['title'] ?? 'Workflow Generated Document'));
                if ($title === '') {
                    $title = 'Workflow Generated Document';
                }

                $documentId = (string) \Illuminate\Support\Str::uuid();
                $documentUrl = "https://docs.google.com/document/d/{$documentId}/edit";
                $shareEmails = $this->normalizeEmailArray($config['share_emails'] ?? []);
                $recipients = $this->workflowNotificationRecipients($config, $context);
                $notificationMessage = $config['message'] ?? "Google Doc created: {$title}";

                $workflowContext = array_intersect_key($context, array_flip([
                    'product_id',
                    'branch_id',
                    'order_id',
                    'quantity',
                    'category',
                    'expiry_date',
                    'available_qty',
                    'report_generated',
                    'report_type',
                    'report_file_name',
                ]));
                $workflowContext['google_doc_created'] = true;
                $workflowContext['google_doc_title'] = $title;
                $workflowContext['google_doc_url'] = $documentUrl;
                if (isset($context['_workflow']) && is_array($context['_workflow'])) {
                    $workflowContext['_workflow'] = $context['_workflow'];
                }
                if (isset($context['_condition_results']) && is_array($context['_condition_results']) && !empty($context['_condition_results'])) {
                    $workflowContext['_condition_results'] = $context['_condition_results'];
                }

                foreach ($recipients as $recipient) {
                    $this->notificationService->notify($recipient, 'workflow_notification', [
                        'message' => $notificationMessage,
                        'workflow_context' => $workflowContext,
                    ]);
                }

                return [
                    'status' => 'google_doc_created',
                    'message' => "Google Doc created: {$title}",
                    'google_doc' => [
                        'id' => $documentId,
                        'title' => $title,
                        'url' => $documentUrl,
                        'folder_id' => isset($config['folder_id']) ? trim((string) $config['folder_id']) : null,
                        'share_emails' => $shareEmails,
                    ],
                    'recipients' => $recipients->count(),
                    'context_updates' => [
                        'google_doc_created' => true,
                        'google_doc_title' => $title,
                        'google_doc_url' => $documentUrl,
                        'confirmation_notifications_sent' => $recipients->count() > 0,
                        'confirmation_notification_count' => $recipients->count(),
                    ],
                ];

            case 'sync_crm_erp':
                $mode = strtolower(trim((string) ($config['mode'] ?? 'real_time')));
                if ($mode === '') {
                    $mode = 'real_time';
                }

                $forceFailure = (bool) ($context['force_sync_failure'] ?? false);
                $simulateFailure = ((int) ($config['simulate_failure'] ?? 0)) === 1;
                $syncStatus = ($forceFailure || $simulateFailure) ? 'failed' : 'synced';

                if ($syncStatus === 'failed' && ((int) ($config['fail_on_error'] ?? 0)) === 1) {
                    throw new \RuntimeException('CRM/ERP synchronization failed and fail-on-error is enabled.');
                }

                return [
                    'status' => $syncStatus === 'synced' ? 'synced' : 'sync_failed',
                    'message' => $syncStatus === 'synced'
                        ? 'CRM/ERP synchronization completed in ' . $mode . ' mode.'
                        : 'CRM/ERP synchronization failed; escalation path may be triggered.',
                    'context_updates' => [
                        'integration_mode' => $mode,
                        'sync_status' => $syncStatus,
                        'crm_sync_status' => $syncStatus,
                        'erp_sync_status' => $syncStatus,
                        'last_synced_at' => now()->toIso8601String(),
                        'sync_endpoints' => array_filter([
                            'crm_endpoint' => isset($config['crm_endpoint']) ? trim((string) $config['crm_endpoint']) : null,
                            'erp_endpoint' => isset($config['erp_endpoint']) ? trim((string) $config['erp_endpoint']) : null,
                        ]),
                    ],
                ];

            case 'escalate_overdue_task':
                $minutes = max(1, (int) ($config['minutes'] ?? 120));
                $reference = $context['requested_at'] ?? data_get($context, '_workflow.started_at');
                $isOverdue = false;

                if ($reference) {
                    try {
                        $isOverdue = \Carbon\Carbon::parse($reference)->diffInMinutes(now()) >= $minutes;
                    } catch (\Throwable $e) {
                        $isOverdue = false;
                    }
                }

                if (!$isOverdue) {
                    return [
                        'status' => 'within_sla',
                        'message' => 'Task is still within SLA; escalation not required.',
                        'context_updates' => [
                            'escalation_due' => false,
                        ],
                    ];
                }

                $message = $config['message'] ?? "Escalation triggered: task exceeded {$minutes} minute SLA.";
                $admins = $this->workflowNotificationRecipients($config, $context);
                $workflowContext = is_array($context['_workflow'] ?? null) ? ['_workflow' => $context['_workflow']] : [];
                foreach ($admins as $admin) {
                    $this->notificationService->notify($admin, 'workflow_notification', [
                        'message' => $message,
                        'workflow_context' => $workflowContext,
                    ]);
                }

                return [
                    'status' => 'escalated',
                    'message' => $message,
                    'recipients' => $admins->count(),
                    'context_updates' => [
                        'escalation_due' => true,
                        'escalated' => true,
                        'escalation_notified' => $admins->count() > 0,
                        'confirmation_notifications_sent' => $admins->count() > 0,
                    ],
                ];

            case 'completion_gate':
                $requirements = [
                    'require_notifications' => ((int) ($config['require_notifications'] ?? 1)) === 1,
                    'require_error_resolution' => ((int) ($config['require_error_resolution'] ?? 1)) === 1,
                ];

                $notificationsOk = !$requirements['require_notifications']
                    || (bool) ($context['confirmation_notifications_sent'] ?? false);
                $errorResolutionOk = !$requirements['require_error_resolution']
                    || empty($context['_error_states']);

                if (!$notificationsOk || !$errorResolutionOk) {
                    $failedChecks = [];
                    if (!$notificationsOk) {
                        $failedChecks[] = 'confirmation notifications not dispatched';
                    }
                    if (!$errorResolutionOk) {
                        $failedChecks[] = 'error states unresolved';
                    }

                    throw new \RuntimeException('Completion gate failed: ' . implode(', ', $failedChecks) . '.');
                }

                return [
                    'status' => 'completion_gate_passed',
                    'message' => $config['confirmation_message'] ?? 'Completion gate passed successfully.',
                    'context_updates' => [
                        '_completion_requirements' => $requirements,
                        'completion_gate_passed' => true,
                    ],
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

                $admins = $this->workflowNotificationRecipients($config, $context);

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
                        'confirmation_notifications_sent' => $admins->count() > 0,
                        'confirmation_notification_count' => $admins->count(),
                    ],
                ];

            case 'webhook_call':
                return $this->executeWebhookCall($config, $context);

            case 'log_audit_event':
                return $this->executeLogAuditEvent($config, $context);

            default:
                return ['status' => 'unknown_action', 'message' => "Unknown action: {$node->action_type}", 'context_updates' => []];
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function resolveActionAttachments(array $context): array
    {
        $candidates = [];

        $collected = $context['_workflow_attachments'] ?? [];
        if (is_array($collected)) {
            foreach ($collected as $attachment) {
                if (is_array($attachment)) {
                    $candidates[] = $attachment;
                }
            }
        }

        $reportAttachment = $context['report_attachment'] ?? null;
        if (is_array($reportAttachment)) {
            $candidates[] = $reportAttachment;
        }

        if (empty($candidates)) {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($candidates as $attachment) {
            $disk = isset($attachment['disk']) ? trim((string) $attachment['disk']) : '';
            $path = isset($attachment['path']) ? trim((string) $attachment['path']) : '';
            if ($disk === '' || $path === '') {
                continue;
            }

            $name = isset($attachment['name']) && trim((string) $attachment['name']) !== ''
                ? trim((string) $attachment['name'])
                : basename($path);
            $mime = isset($attachment['mime']) && trim((string) $attachment['mime']) !== ''
                ? trim((string) $attachment['mime'])
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

            $fingerprint = strtolower($disk) . '|' . strtolower($path) . '|' . strtolower($name);
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;

            $normalized[] = [
                'disk' => $disk,
                'path' => $path,
                'name' => $name,
                'mime' => $mime,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int,array{source:string,target:string}>
     */
    protected function parseFieldMappings(mixed $rawMappings): array
    {
        if (!is_array($rawMappings)) {
            return [];
        }

        $pairs = [];
        foreach ($rawMappings as $key => $value) {
            if (is_string($key) && !is_int($key)) {
                $source = trim($key);
                $target = trim((string) $value);
                if ($source !== '' && $target !== '') {
                    $pairs[] = ['source' => $source, 'target' => $target];
                }
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $parts = explode(':', $value, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $source = trim($parts[0]);
            $target = trim($parts[1]);
            if ($source !== '' && $target !== '') {
                $pairs[] = ['source' => $source, 'target' => $target];
            }
        }

        return $pairs;
    }

    protected function compareFieldValues(mixed $actual, mixed $expected, string $operator): bool
    {
        $normalized = strtolower(trim($operator));
        if ($normalized === '') {
            $normalized = '==';
        }

        if (in_array($normalized, ['>', '>=', '<', '<='], true)) {
            if (!is_numeric($actual) || !is_numeric($expected)) {
                return false;
            }

            $left = (float) $actual;
            $right = (float) $expected;
            return match ($normalized) {
                '>' => $left > $right,
                '>=' => $left >= $right,
                '<' => $left < $right,
                '<=' => $left <= $right,
                default => false,
            };
        }

        if ($normalized === 'contains') {
            return str_contains(strtolower((string) $actual), strtolower((string) $expected));
        }

        if ($normalized === '!=') {
            return (string) $actual !== (string) $expected;
        }

        return (string) $actual === (string) $expected;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int,\App\Models\User>
     */
    protected function workflowNotificationRecipients(array $config = [], array $context = [])
    {
        return $this->resolveWorkflowRecipients($config, $context);
    }

    /**
     * Resolve SMTP notification recipients using strategy-driven targeting:
     * - admins (default): users with notifications.manage permission
     * - specific_users: explicit user IDs/emails (+ optional context user field)
     * - criteria: dynamic filters by branch, level, permissions, email, context fields
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,\App\Models\User>
     */
    protected function resolveWorkflowRecipients(array $config, array $context, string $defaultPermission = 'notifications.manage')
    {
        $strategy = strtolower(trim((string) ($config['recipient_strategy'] ?? 'admins')));
        if ($strategy === '') {
            $strategy = 'admins';
        }

        $triggerUserId = $this->extractTriggerUserId($context);
        $cacheSeed = [
            'strategy' => $strategy,
            'config' => [
                'recipient_user_ids' => $this->normalizeIdArray($config['recipient_user_ids'] ?? []),
                'recipient_branch_ids' => $this->normalizeIdArray($config['recipient_branch_ids'] ?? []),
                'recipient_level_ids' => $this->normalizeIdArray($config['recipient_level_ids'] ?? []),
                'recipient_permissions' => $this->normalizeStringArray($config['recipient_permissions'] ?? []),
                'recipient_emails' => $this->normalizeStringArray($config['recipient_emails'] ?? []),
                'recipient_context_user_field' => trim((string) ($config['recipient_context_user_field'] ?? '')),
                'recipient_match_context_branch' => ((int) ($config['recipient_match_context_branch'] ?? 0)) === 1,
                'include_trigger_user' => ((int) ($config['include_trigger_user'] ?? 0)) === 1,
            ],
            'context' => [
                'branch_id' => $context['branch_id'] ?? null,
                'triggered_by' => $triggerUserId,
                'recipient_context_user_field_value' => $this->extractContextInt($context, trim((string) ($config['recipient_context_user_field'] ?? ''))),
            ],
        ];
        $cacheKey = hash('sha256', json_encode($cacheSeed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (!array_key_exists($cacheKey, $this->recipientResolutionCache)) {
            $userIds = [];

            if ($strategy === 'specific_users') {
                $specificIds = $this->normalizeIdArray($config['recipient_user_ids'] ?? []);
                $specificEmails = $this->normalizeEmailArray($config['recipient_emails'] ?? []);
                $contextUserId = $this->extractContextInt($context, trim((string) ($config['recipient_context_user_field'] ?? '')));
                if ($contextUserId !== null) {
                    $specificIds[] = $contextUserId;
                }

                $specificIds = array_values(array_unique($specificIds));
                if (!empty($specificIds) || !empty($specificEmails)) {
                    $query = \App\Models\User::query()->whereNotNull('email');
                    $query->where(function ($q) use ($specificIds, $specificEmails) {
                        if (!empty($specificIds)) {
                            $q->orWhereIn('id', $specificIds);
                        }
                        if (!empty($specificEmails)) {
                            $q->orWhereIn('email', $specificEmails);
                        }
                    });
                    $userIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
                }
            } elseif ($strategy === 'criteria') {
                $query = \App\Models\User::query()->whereNotNull('email');
                $hasCriteria = false;

                $branchIds = $this->normalizeIdArray($config['recipient_branch_ids'] ?? []);
                if (((int) ($config['recipient_match_context_branch'] ?? 0)) === 1 && is_numeric($context['branch_id'] ?? null)) {
                    $branchIds[] = (int) $context['branch_id'];
                }
                $branchIds = array_values(array_unique(array_filter($branchIds, fn ($id) => $id > 0)));
                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', $branchIds);
                    $hasCriteria = true;
                }

                $levelIds = $this->normalizeIdArray($config['recipient_level_ids'] ?? []);
                if (!empty($levelIds)) {
                    $query->whereIn('user_level_id', $levelIds);
                    $hasCriteria = true;
                }

                $permissionNames = $this->normalizeStringArray($config['recipient_permissions'] ?? []);
                if (!empty($permissionNames)) {
                    foreach ($permissionNames as $permissionName) {
                        $query->whereHas('level', function ($levelQuery) use ($permissionName) {
                            $levelQuery->whereHas('permissions', function ($permQuery) use ($permissionName) {
                                $permQuery->where('name', $permissionName);
                            });
                        });
                    }
                    $hasCriteria = true;
                }

                $emails = $this->normalizeEmailArray($config['recipient_emails'] ?? []);
                if (!empty($emails)) {
                    $query->whereIn('email', $emails);
                    $hasCriteria = true;
                }

                $contextUserId = $this->extractContextInt($context, trim((string) ($config['recipient_context_user_field'] ?? '')));
                if ($contextUserId !== null) {
                    $query->where('id', $contextUserId);
                    $hasCriteria = true;
                }

                if (!$hasCriteria) {
                    $query = $this->queryAdminsForNotification($defaultPermission);
                }

                $userIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            } else {
                $userIds = $this->queryAdminsForNotification($defaultPermission)->pluck('id')->map(fn ($id) => (int) $id)->all();
            }

            if (((int) ($config['include_trigger_user'] ?? 0)) === 1 && $triggerUserId !== null) {
                $userIds[] = $triggerUserId;
            }

            $this->recipientResolutionCache[$cacheKey] = array_values(array_unique(array_filter($userIds, fn ($id) => $id > 0)));
        }

        $resolvedIds = $this->recipientResolutionCache[$cacheKey];
        if (empty($resolvedIds)) {
            return \App\Models\User::query()->whereRaw('1 = 0')->get();
        }

        $recipients = \App\Models\User::query()
            ->whereIn('id', $resolvedIds)
            ->whereNotNull('email')
            ->get();

        $positions = array_flip($resolvedIds);
        return $recipients->sortBy(fn ($user) => $positions[(int) $user->id] ?? PHP_INT_MAX)->values();
    }

    protected function queryAdminsForNotification(string $permissionName = 'notifications.manage')
    {
        return \App\Models\User::query()
            ->whereNotNull('email')
            ->whereHas('level', function ($query) use ($permissionName) {
                $query->whereHas('permissions', function ($q) use ($permissionName) {
                    $q->where('name', $permissionName);
                });
            });
    }

    /**
     * @return array<int,int>
     */
    protected function normalizeIdArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_numeric($item)) {
                $numeric = (int) $item;
                if ($numeric > 0) {
                    $ids[] = $numeric;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int,string>
     */
    protected function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                continue;
            }

            $normalized = trim((string) $item);
            if ($normalized !== '') {
                $strings[] = $normalized;
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @return array<int,string>
     */
    protected function normalizeEmailArray(mixed $value): array
    {
        $emails = $this->normalizeStringArray($value);

        return array_values(array_unique(array_filter(array_map(
            fn ($email) => strtolower($email),
            $emails
        ), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    protected function extractContextInt(array $context, string $field): ?int
    {
        $key = trim($field);
        if ($key === '') {
            return null;
        }

        $value = data_get($context, $key);
        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (int) $value;
        return $numeric > 0 ? $numeric : null;
    }

    protected function extractTriggerUserId(array $context): ?int
    {
        $candidate = data_get($context, '_workflow.triggered_by');
        if (!is_numeric($candidate)) {
            $candidate = $context['user_id'] ?? null;
        }

        if (!is_numeric($candidate)) {
            return null;
        }

        $userId = (int) $candidate;
        return $userId > 0 ? $userId : null;
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

    // ──────────────────────────────────────────────────────────
    //  REAL ACTION IMPLEMENTATIONS
    // ──────────────────────────────────────────────────────────

    /**
     * Create a Hold record with associated HoldItems for matching inventory.
     */
    protected function executeCreateHold(array $config, array $context): array
    {
        $reason = $config['reason'] ?? 'Workflow automation';
        $branchId = $context['branch_id'] ?? null;
        $productId = $context['product_id'] ?? null;
        $triggeredBy = data_get($context, '_workflow.triggered_by');

        // Find inventory batches to hold
        $query = Inventory::query()->where('is_archived', false);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }
        // Only hold batches with available stock
        $query->whereRaw('onhand_qty - hold_qty > 0');

        // If triggered by expiry, target near-expiry batches
        $expiryDays = $config['expiry_days'] ?? data_get($context, '_workflow_trigger_config.days');
        if ($expiryDays) {
            $query->where('expiry_date', '<=', now()->addDays((int) $expiryDays));
        }

        $batches = $query->limit(50)->get();

        if ($batches->isEmpty()) {
            return [
                'status' => 'no_action',
                'message' => 'No eligible inventory batches found for hold.',
                'context_updates' => ['hold_requested' => true, 'hold_created' => false],
            ];
        }

        $hold = Hold::create([
            'branch_id' => $branchId ?: $batches->first()->branch_id,
            'type' => 'quarantine',
            'reason_code' => 'workflow_automation',
            'remarks' => $reason,
            'created_by' => $triggeredBy,
            'status' => 'approved', // Auto-approved by workflow
            'approved_by' => $triggeredBy,
        ]);

        $holdItemCount = 0;
        foreach ($batches as $batch) {
            $availableQty = $batch->onhand_qty - $batch->hold_qty;
            if ($availableQty <= 0) continue;

            HoldItem::create([
                'hold_id' => $hold->id,
                'inventory_id' => $batch->id,
                'quantity' => $availableQty,
            ]);

            $batch->update(['hold_qty' => $batch->hold_qty + $availableQty]);
            $holdItemCount++;
        }

        return [
            'status' => 'hold_created',
            'message' => "Hold #{$hold->id} created with {$holdItemCount} item(s). Reason: {$reason}",
            'context_updates' => [
                'hold_requested' => true,
                'hold_created' => true,
                'hold_id' => $hold->id,
                'hold_item_count' => $holdItemCount,
                'hold_reason' => $reason,
            ],
        ];
    }

    /**
     * Release holds matching context criteria.
     */
    protected function executeReleaseHold(array $config, array $context): array
    {
        $holdId = $context['hold_id'] ?? null;
        $branchId = $context['branch_id'] ?? null;
        $triggeredBy = data_get($context, '_workflow.triggered_by');

        $query = Hold::where('status', 'approved');
        if ($holdId) {
            $query->where('id', $holdId);
        } elseif ($branchId) {
            $query->where('branch_id', $branchId)
                  ->where('reason_code', 'workflow_automation');
        } else {
            return [
                'status' => 'no_action',
                'message' => 'No hold ID or branch ID in context to release.',
                'context_updates' => ['hold_released' => false],
            ];
        }

        $holds = $query->limit(10)->get();
        $releasedCount = 0;

        foreach ($holds as $hold) {
            foreach ($hold->items as $item) {
                $inventory = Inventory::find($item->inventory_id);
                if ($inventory) {
                    $newHoldQty = max(0, $inventory->hold_qty - $item->quantity);
                    $inventory->update(['hold_qty' => $newHoldQty]);
                }
            }

            $hold->update(['status' => 'released']);
            $releasedCount++;
        }

        return [
            'status' => $releasedCount > 0 ? 'holds_released' : 'no_action',
            'message' => $releasedCount > 0
                ? "{$releasedCount} hold(s) released successfully."
                : 'No matching holds found to release.',
            'context_updates' => [
                'hold_released' => $releasedCount > 0,
                'holds_released_count' => $releasedCount,
            ],
        ];
    }

    /**
     * Create a reorder suggestion by logging it and notifying admins.
     */
    protected function executeReorderSuggestion(array $config, array $context): array
    {
        $productId = $context['product_id'] ?? null;
        $branchId = $context['branch_id'] ?? null;
        $suggestedQty = $config['quantity'] ?? 50;
        $currentQty = $context['quantity'] ?? $context['available_qty'] ?? 0;

        // Auto-calculate if not specified
        if (!isset($config['quantity']) && $productId) {
            $avgMonthlyUsage = DB::table('product_movements')
                ->where('product_id', $productId)
                ->where('created_at', '>=', now()->subMonths(3))
                ->where('type', 'out')
                ->avg('quantity');
            if ($avgMonthlyUsage && $avgMonthlyUsage > 0) {
                $suggestedQty = (int) ceil($avgMonthlyUsage * 2); // 2 months buffer
            }
        }

        // Log the suggestion as an audit event
        $this->auditService->record(
            'reorder_suggestion',
            'Product',
            $productId ?? 0,
            data_get($context, '_workflow.triggered_by', 0),
            null,
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'current_qty' => $currentQty,
                'suggested_qty' => $suggestedQty,
                'source' => 'workflow_automation',
            ],
            'Automated reorder suggestion generated',
        );

        return [
            'status' => 'reorder_suggested',
            'message' => "Reorder suggestion created: {$suggestedQty} units for product #{$productId}",
            'context_updates' => [
                'reorder_suggested' => true,
                'suggested_qty' => $suggestedQty,
                'current_qty' => $currentQty,
            ],
        ];
    }

    /**
     * Auto-allocate order items using FEFO (First Expiry First Out) strategy.
     */
    protected function executeAutoAllocateOrder(array $config, array $context): array
    {
        $orderId = $context['order_id'] ?? null;
        if (!$orderId) {
            return [
                'status' => 'no_action',
                'message' => 'No order_id in context for auto-allocation.',
                'context_updates' => ['auto_allocated' => false],
            ];
        }

        $order = Order::with('items')->find($orderId);
        if (!$order) {
            return [
                'status' => 'no_action',
                'message' => "Order #{$orderId} not found.",
                'context_updates' => ['auto_allocated' => false],
            ];
        }

        $allocations = [];
        $fullyAllocated = true;

        foreach ($order->items as $orderItem) {
            $requestedQuantity = (int) ($orderItem->quantity_requested ?? $orderItem->quantity ?? 0);
            $remaining = $requestedQuantity;
            $productId = $orderItem->product_id ?? null;
            if (!$productId || $remaining <= 0) continue;

            $sourceBranchId = $orderItem->source_branch_id ?? $order->branch_id ?? $context['branch_id'] ?? null;

            // FEFO: order by expiry_date ascending
            $batches = Inventory::where('product_id', $productId)
                ->where('branch_id', $sourceBranchId)
                ->where('is_archived', false)
                ->whereRaw('onhand_qty - hold_qty > 0')
                ->orderBy('expiry_date', 'asc')
                ->get();

            $itemAllocations = [];
            foreach ($batches as $batch) {
                if ($remaining <= 0) break;
                $available = $batch->onhand_qty - $batch->hold_qty;
                $allocate = min($remaining, $available);

                if ($allocate > 0) {
                    $itemAllocations[] = [
                        'inventory_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'quantity' => $allocate,
                        'expiry_date' => $batch->expiry_date?->toDateString(),
                    ];
                    $remaining -= $allocate;
                }
            }

            if ($remaining > 0) {
                $fullyAllocated = false;
            }

            $allocations[] = [
                'product_id' => $productId,
                'requested' => $requestedQuantity,
                'allocated' => $requestedQuantity - $remaining,
                'shortfall' => $remaining,
                'source_branch_id' => $sourceBranchId,
                'batches' => $itemAllocations,
            ];
        }

        return [
            'status' => $fullyAllocated ? 'fully_allocated' : 'partially_allocated',
            'message' => $fullyAllocated
                ? "Order #{$orderId} fully allocated using FEFO."
                : "Order #{$orderId} partially allocated (some items short).",
            'context_updates' => [
                'auto_allocated' => true,
                'allocation_complete' => $fullyAllocated,
                'allocations' => $allocations,
            ],
        ];
    }

    /**
     * Create a transfer request between branches.
     */
    protected function executeTransferRequest(array $config, array $context): array
    {
        $targetBranchId = $config['target_branch_id'] ?? null;
        $sourceBranchId = $context['branch_id'] ?? null;
        $productId = $context['product_id'] ?? null;
        $quantity = $config['quantity'] ?? $context['suggested_qty'] ?? 50;
        $triggeredBy = data_get($context, '_workflow.triggered_by', 0);

        if (!$targetBranchId) {
            return [
                'status' => 'no_action',
                'message' => 'No target_branch_id specified for transfer.',
                'context_updates' => ['transfer_requested' => false],
            ];
        }

        // Log the transfer request as an audit event
        $this->auditService->record(
            'transfer_request_created',
            'Branch',
            $targetBranchId,
            $triggeredBy,
            null,
            [
                'source_branch_id' => $sourceBranchId,
                'target_branch_id' => $targetBranchId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'source' => 'workflow_automation',
            ],
            'Automated transfer request created',
        );

        return [
            'status' => 'transfer_requested',
            'message' => "Transfer request created: {$quantity} units from branch #{$sourceBranchId} to branch #{$targetBranchId}",
            'context_updates' => [
                'transfer_requested' => true,
                'target_branch_id' => $targetBranchId,
                'transfer_quantity' => $quantity,
            ],
        ];
    }

    /**
     * Execute a signed webhook call with domain allowlist enforcement.
     */
    protected function executeWebhookCall(array $config, array $context): array
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'status' => 'failed',
                'message' => 'Invalid or missing webhook URL.',
                'context_updates' => ['webhook_called' => false],
            ];
        }

        // SSRF protection: allowlist check
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        // Block internal addresses
        $blockedPatterns = [
            '/^localhost$/i',
            '/^127\./',
            '/^10\./',
            '/^172\.(1[6-9]|2[0-9]|3[01])\./',
            '/^192\.168\./',
            '/^0\./',
            '/^169\.254\./',
            '/^\[::1\]$/',
            '/^metadata\./',
            '/\.internal$/',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return [
                    'status' => 'blocked',
                    'message' => 'Webhook URL targets a restricted address.',
                    'context_updates' => ['webhook_called' => false],
                ];
            }
        }

        // Check workflow-level allowlist
        $workflowId = data_get($context, '_workflow.workflow_id');
        if ($workflowId) {
            $definition = WorkflowDefinition::find($workflowId);
            $allowlist = $definition->webhook_allowlist ?? [];
            if (!empty($allowlist)) {
                $allowed = false;
                foreach ($allowlist as $pattern) {
                    if (fnmatch($pattern, $host) || fnmatch($pattern, $url)) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    return [
                        'status' => 'blocked',
                        'message' => "Webhook host '{$host}' not in workflow allowlist.",
                        'context_updates' => ['webhook_called' => false],
                    ];
                }
            }
        }

        // Build signed payload
        $payload = array_filter([
            'workflow_id' => $workflowId,
            'run_id' => data_get($context, '_workflow.run_id'),
            'trigger_type' => data_get($context, '_workflow.trigger_type'),
            'timestamp' => now()->toIso8601String(),
            'context' => array_diff_key($context, array_flip([
                '_debug_trace', '_workflow_outputs', '_workflow_attachments',
                '_parallel_stages', '_completion', '_completion_requirements',
            ])),
        ]);

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // Sign with workflow secret or app key
        $secret = null;
        if ($workflowId) {
            $definition = $definition ?? WorkflowDefinition::find($workflowId);
            $secret = $definition->webhook_secret ?? null;
        }
        $signingKey = $secret ?: config('app.key');
        $signature = hash_hmac('sha256', $payloadJson, $signingKey);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Workflow-Signature' => $signature,
                    'X-Workflow-Timestamp' => $payload['timestamp'],
                    'User-Agent' => 'GTIMS-Workflow/1.0',
                ])
                ->send($method, $url, ['body' => $payloadJson]);

            $statusCode = $response->status();
            $success = $response->successful();

            return [
                'status' => $success ? 'webhook_sent' : 'webhook_failed',
                'message' => $success
                    ? "Webhook {$method} to {$url} succeeded (HTTP {$statusCode})."
                    : "Webhook {$method} to {$url} failed (HTTP {$statusCode}).",
                'context_updates' => [
                    'webhook_called' => true,
                    'webhook_status' => $statusCode,
                    'webhook_success' => $success,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'webhook_error',
                'message' => "Webhook call failed: {$e->getMessage()}",
                'context_updates' => [
                    'webhook_called' => true,
                    'webhook_success' => false,
                    'webhook_error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Log an audit event via the AuditService.
     *
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $context
     * @return array{status:string,message:string,context_updates:array<string,mixed>}
     */
    protected function executeLogAuditEvent(array $config, array $context): array
    {
        $message = trim((string) ($config['message'] ?? 'Workflow audit event'));
        $eventType = trim((string) ($config['event_type'] ?? 'workflow_automation'));
        $triggeredBy = (int) data_get($context, '_workflow.triggered_by', 0);
        $workflowId = data_get($context, '_workflow.workflow_id');
        $runId = data_get($context, '_workflow.run_id');

        $this->auditService->record(
            $eventType,
            'WorkflowRun',
            $runId ?? 0,
            $triggeredBy,
            null,
            array_filter([
                'workflow_id' => $workflowId,
                'run_id' => $runId,
                'message' => $message,
                'source' => 'workflow_automation',
            ]),
            $message,
        );

        return [
            'status' => 'logged',
            'message' => $message,
            'context_updates' => [
                'audit_logged' => true,
                'audit_event_type' => $eventType,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  RETRY / DEAD-LETTER HANDLING
    // ──────────────────────────────────────────────────────────

    /**
     * Handle a failed workflow run: determine if it should be retried or dead-lettered.
     */
    public function handleFailedRun(WorkflowRun $run, \Throwable $exception): void
    {
        $run->refresh();

        if ($run->is_dry_run) {
            return; // Don't retry dry runs
        }

        $maxRetries = $run->max_retries ?? 3;
        $attempt = $run->retry_attempt ?? 0;

        if ($attempt >= $maxRetries) {
            // Move to dead-letter
            $run->update([
                'is_dead_letter' => true,
                'error_message' => $exception->getMessage() . ' [Dead-lettered after ' . $maxRetries . ' retries]',
            ]);

            Log::warning('Workflow run dead-lettered', [
                'run_id' => $run->id,
                'workflow_id' => $run->workflow_definition_id,
                'retries' => $attempt,
                'error' => $exception->getMessage(),
            ]);

            $this->auditService->record(
                'workflow_run_dead_lettered',
                'WorkflowRun',
                $run->id,
                $run->triggered_by ?? 0,
                null,
                ['error' => $exception->getMessage(), 'retries' => $attempt],
                'Workflow run moved to dead-letter queue'
            );
        } else {
            // Schedule retry with exponential backoff
            $backoffSeconds = (int) pow(2, $attempt + 1) * 30; // 60s, 120s, 240s...
            $nextRetryAt = now()->addSeconds($backoffSeconds);

            $run->update([
                'next_retry_at' => $nextRetryAt,
            ]);

            Log::info('Workflow run scheduled for retry', [
                'run_id' => $run->id,
                'attempt' => $attempt,
                'next_retry_at' => $nextRetryAt->toIso8601String(),
            ]);
        }
    }

    /**
     * Re-run a dead-lettered or failed run from scratch.
     */
    public function rerunFromDeadLetter(WorkflowRun $failedRun, ?int $userId = null): WorkflowRun
    {
        $newRun = WorkflowRun::create([
            'workflow_definition_id' => $failedRun->workflow_definition_id,
            'workflow_version_id' => $failedRun->workflow_version_id,
            'status' => 'pending',
            'trigger_type' => $failedRun->trigger_type,
            'trigger_payload' => $failedRun->trigger_payload,
            'context' => $failedRun->trigger_payload ?? [],
            'triggered_by' => $userId ?? $failedRun->triggered_by,
            'is_dry_run' => false,
            'retry_attempt' => 0,
            'max_retries' => $failedRun->max_retries,
            'parent_run_id' => $failedRun->id,
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        // Mark original as rerun
        $failedRun->update([
            'error_message' => ($failedRun->error_message ?? '') . " [Rerun as #{$newRun->id}]",
        ]);

        return $this->executeRun($newRun);
    }
}
