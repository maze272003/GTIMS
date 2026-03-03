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
}
