<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NotificationService
{
    /**
     * Send a notification to a user based on their preferences.
     */
    public function notify(User $user, string $type, array $data): void
    {
        $pref = NotificationPreference::where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        // Default: email enabled
        $emailEnabled = $pref ? $pref->email_enabled : true;

        if ($emailEnabled) {
            $this->sendEmail($user, $type, $data);
        }

        Log::channel('daily')->info("Notification sent", [
            'user_id' => $user->id,
            'type' => $type,
            'email_sent' => $emailEnabled,
        ]);
    }

    /**
     * Send email notification.
     */
    protected function sendEmail(User $user, string $type, array $data): void
    {
        try {
            $subject = $this->buildSubject($type, $data);
            $attachments = $this->resolveAttachments($data);

            Mail::raw($this->buildMessage($type, $data), function ($message) use ($user, $subject, $attachments) {
                $message->to($user->email)
                    ->subject($subject);

                foreach ($attachments as $attachment) {
                    $options = [
                        'as' => $attachment['name'] ?? basename($attachment['absolute_path']),
                    ];

                    if (isset($attachment['mime']) && $attachment['mime'] !== '') {
                        $options['mime'] = $attachment['mime'];
                    }

                    $message->attach($attachment['absolute_path'], $options);
                }
            });
        } catch (\Exception $e) {
            Log::error("Failed to send email notification", [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build notification message based on type.
     */
    protected function buildMessage(string $type, array $data): string
    {
        $safe = array_map(fn($v) => is_string($v) ? strip_tags($v) : $v, $data);

        return match ($type) {
            'low_stock' => "Low stock alert: {$safe['product_name']} is below threshold ({$safe['available']} available, threshold: {$safe['threshold']}).",
            'approval_needed' => "A new request #{$safe['request_id']} requires your approval.",
            'hold_expiry' => "Hold #{$safe['hold_id']} is expiring soon.",
            'request_status' => "Request #{$safe['request_id']} status changed to {$safe['status']}.",
            'workflow_notification' => $this->buildWorkflowMessage($safe),
            default => "GTIMS Notification: " . json_encode($safe),
        };
    }

    protected function buildSubject(string $type, array $data): string
    {
        if ($type === 'workflow_notification') {
            $context = $data['workflow_context'] ?? [];
            if (is_array($context) && isset($context['_workflow']) && is_array($context['_workflow'])) {
                $workflowName = $context['_workflow']['workflow_name'] ?? null;
                $runId = $context['_workflow']['run_id'] ?? null;
                if ($workflowName && $runId) {
                    return "GTIMS Workflow Alert: {$workflowName} (Run #{$runId})";
                }
                if ($workflowName) {
                    return "GTIMS Workflow Alert: {$workflowName}";
                }
            }

            return "GTIMS Workflow Alert";
        }

        return "GTIMS Notification: {$type}";
    }

    protected function buildWorkflowMessage(array $safe): string
    {
        $message = isset($safe['message']) && is_string($safe['message']) && trim($safe['message']) !== ''
            ? $safe['message']
            : 'Workflow alert generated.';

        $context = $safe['workflow_context'] ?? [];
        if (!is_array($context)) {
            $context = [];
        }

        $workflowMeta = [];
        if (isset($context['_workflow']) && is_array($context['_workflow'])) {
            $workflowMeta = $context['_workflow'];
            unset($context['_workflow']);
        }

        $conditionMeta = [];
        if (isset($context['_condition_results']) && is_array($context['_condition_results'])) {
            $conditionMeta = $context['_condition_results'];
            unset($context['_condition_results']);
        }

        $workflowOutputs = [];
        if (isset($context['_workflow_outputs']) && is_array($context['_workflow_outputs'])) {
            $workflowOutputs = $context['_workflow_outputs'];
            unset($context['_workflow_outputs']);
        }

        $lines = [
            'Workflow Automation Alert',
            "Message: {$message}",
        ];

        $workflowName = $workflowMeta['workflow_name'] ?? null;
        $runId = $workflowMeta['run_id'] ?? null;
        $versionId = $workflowMeta['workflow_version_id'] ?? null;
        if ($workflowName || $runId || $versionId) {
            $runText = $runId ? "#{$runId}" : 'n/a';
            $versionText = $versionId ? "#{$versionId}" : 'n/a';
            $nameText = $workflowName ?: 'Unknown Workflow';
            $lines[] = "Workflow: {$nameText}";
            $lines[] = "Run ID: {$runText}";
            $lines[] = "Version ID: {$versionText}";
        }

        if (!empty($context)) {
            $lines[] = '';
            $lines[] = 'Automation Context:';
            $labels = [
                'product_id' => 'Product ID',
                'branch_id' => 'Branch ID',
                'order_id' => 'Order ID',
                'quantity' => 'Quantity',
                'available_qty' => 'Available Quantity',
                'category' => 'Category',
                'expiry_date' => 'Expiry Date',
                'hold_requested' => 'Hold Requested',
                'hold_reason' => 'Hold Reason',
                'transfer_requested' => 'Transfer Requested',
                'target_branch_id' => 'Target Branch ID',
                'report_generated' => 'Report Generated',
                'report_type' => 'Report Type',
                'report_file_name' => 'Report File',
                'google_doc_created' => 'Google Doc Created',
                'google_doc_title' => 'Google Doc Title',
                'google_doc_url' => 'Google Doc URL',
                'webhook_called' => 'Webhook Called',
            ];

            foreach ($labels as $key => $label) {
                if (!array_key_exists($key, $context)) {
                    continue;
                }

                $value = $context[$key];
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                }

                $lines[] = "- {$label}: {$value}";
            }
        } else {
            $lines[] = '';
            $lines[] = 'Automation Context: No runtime payload was provided.';
        }

        if (!empty($conditionMeta)) {
            $lines[] = '';
            $lines[] = 'Condition Results:';
            foreach ($conditionMeta as $nodeId => $result) {
                $lines[] = "- {$nodeId}: " . ($result ? 'TRUE' : 'FALSE');
            }
        }

        if (!empty($workflowOutputs)) {
            $lines[] = '';
            $lines[] = 'Workflow Outputs:';
            foreach (array_slice($workflowOutputs, -15) as $output) {
                if (!is_array($output)) {
                    continue;
                }

                $actionType = (string) ($output['action_type'] ?? 'action');
                $nodeId = (string) ($output['node_id'] ?? 'node');
                $status = strtoupper((string) ($output['status'] ?? 'done'));
                $messageText = trim((string) ($output['message'] ?? ''));
                $lines[] = "- {$status} {$actionType} ({$nodeId})" . ($messageText !== '' ? ": {$messageText}" : '');

                $googleDoc = $output['google_doc'] ?? null;
                if (is_array($googleDoc) && isset($googleDoc['url'])) {
                    $lines[] = "  Google Doc: " . (string) $googleDoc['url'];
                }

                $report = $output['report'] ?? null;
                if (is_array($report) && isset($report['file_name'])) {
                    $lines[] = "  Report File: " . (string) $report['file_name'];
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<int,array<string,string>>
     */
    protected function resolveAttachments(array $data): array
    {
        $attachments = $data['attachments'] ?? [];
        if (!is_array($attachments)) {
            return [];
        }

        $resolved = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $absolutePath = null;
            $name = isset($attachment['name']) ? trim((string) $attachment['name']) : '';
            $mime = isset($attachment['mime']) ? trim((string) $attachment['mime']) : '';

            $disk = isset($attachment['disk']) ? trim((string) $attachment['disk']) : '';
            $relativePath = isset($attachment['path']) ? trim((string) $attachment['path']) : '';
            if ($disk !== '' && $relativePath !== '' && Storage::disk($disk)->exists($relativePath)) {
                $absolutePath = Storage::disk($disk)->path($relativePath);
            }

            $absolutePathFromData = isset($attachment['absolute_path']) ? trim((string) $attachment['absolute_path']) : '';
            if ($absolutePath === null && $absolutePathFromData !== '' && is_file($absolutePathFromData)) {
                $absolutePath = $absolutePathFromData;
            }

            if ($absolutePath === null) {
                continue;
            }

            $resolved[] = array_filter([
                'absolute_path' => $absolutePath,
                'name' => $name,
                'mime' => $mime,
            ], fn ($value) => $value !== '');
        }

        return $resolved;
    }

    /**
     * Notify all admins about low stock.
     */
    public function notifyLowStock(int $productId, string $productName, int $available, int $threshold): void
    {
        $admins = User::whereHas('level', function ($query) {
            $query->whereHas('permissions', function ($q) {
                $q->where('name', 'notifications.manage');
            });
        })->get();
        foreach ($admins as $admin) {
            $this->notify($admin, 'low_stock', [
                'product_id' => $productId,
                'product_name' => $productName,
                'available' => $available,
                'threshold' => $threshold,
            ]);
        }
    }
}
