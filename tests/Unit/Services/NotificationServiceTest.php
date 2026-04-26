<?php

namespace Tests\Unit\Services;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    public function test_workflow_notification_message_is_human_readable(): void
    {
        $service = app(NotificationService::class);

        $method = new \ReflectionMethod(NotificationService::class, 'buildMessage');
        $method->setAccessible(true);

        $message = $method->invoke($service, 'workflow_notification', [
            'message' => 'Workflow alert generated.',
            'workflow_context' => [
                'product_id' => 15,
                'branch_id' => 2,
                'order_id' => 55,
                '_workflow' => [
                    'workflow_name' => 'Low Stock Automation',
                    'run_id' => 777,
                    'workflow_version_id' => 4,
                ],
            ],
        ]);

        $this->assertStringContainsString('Workflow Automation Alert', $message);
        $this->assertStringContainsString('Workflow: Low Stock Automation', $message);
        $this->assertStringContainsString('Run ID: #777', $message);
        $this->assertStringContainsString('- Product ID: 15', $message);
        $this->assertStringContainsString('- Branch ID: 2', $message);
        $this->assertStringContainsString('- Order ID: 55', $message);
    }

    public function test_resolve_attachments_uses_disk_and_path(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('automation-reports/sample.xlsx', 'report-binary');

        $service = app(NotificationService::class);
        $method = new \ReflectionMethod(NotificationService::class, 'resolveAttachments');
        $method->setAccessible(true);

        $attachments = $method->invoke($service, [
            'attachments' => [[
                'disk' => 'local',
                'path' => 'automation-reports/sample.xlsx',
                'name' => 'sample.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]],
        ]);

        $this->assertCount(1, $attachments);
        $this->assertStringEndsWith('sample.xlsx', $attachments[0]['name']);
        $this->assertStringContainsString('automation-reports', $attachments[0]['absolute_path']);
    }

    public function test_workflow_notification_message_includes_google_doc_and_output_summary(): void
    {
        $service = app(NotificationService::class);

        $method = new \ReflectionMethod(NotificationService::class, 'buildMessage');
        $method->setAccessible(true);

        $message = $method->invoke($service, 'workflow_notification', [
            'message' => 'Automation outputs generated.',
            'workflow_context' => [
                'google_doc_created' => true,
                'google_doc_title' => 'Onboarding Packet',
                'google_doc_url' => 'https://docs.google.com/document/d/test-doc/edit',
                '_workflow_outputs' => [
                    [
                        'node_id' => 'action_1',
                        'action_type' => 'create_google_doc',
                        'status' => 'google_doc_created',
                        'message' => 'Google Doc created: Onboarding Packet',
                        'google_doc' => [
                            'url' => 'https://docs.google.com/document/d/test-doc/edit',
                        ],
                    ],
                    [
                        'node_id' => 'action_2',
                        'action_type' => 'generate_report',
                        'status' => 'report_generated',
                        'message' => 'Report generated: sample.xlsx',
                        'report' => [
                            'file_name' => 'sample.xlsx',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('Google Doc URL', $message);
        $this->assertStringContainsString('Workflow Outputs:', $message);
        $this->assertStringContainsString('create_google_doc', $message);
        $this->assertStringContainsString('sample.xlsx', $message);
    }
}
