<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationPreference;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send a notification to a user based on their preferences.
     */
    public function notify(User $user, string $type, array $data, ?TenantContext $tenantContext = null): void
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
            'tenant_scope' => $tenantContext?->scopeType,
            'province_id' => $tenantContext?->provinceId,
            'barangay_id' => $tenantContext?->barangayId,
        ]);
    }

    /**
     * Send email notification.
     */
    protected function sendEmail(User $user, string $type, array $data): void
    {
        try {
            Mail::raw($this->buildMessage($type, $data), function ($message) use ($user, $type) {
                $message->to($user->email)
                    ->subject("GTIMS Notification: {$type}");
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
            default => "GTIMS Notification: " . json_encode($safe),
        };
    }

    /**
     * Notify all admins about low stock.
     */
    public function notifyLowStock(
        int $productId,
        string $productName,
        int $available,
        int $threshold,
        ?TenantContext $tenantContext = null
    ): void
    {
        if (!$tenantContext && app()->bound(TenantContext::class)) {
            $tenantContext = app(TenantContext::class);
        }

        $admins = User::whereHas('level', function ($query) {
            $query->whereHas('permissions', function ($q) {
                $q->where('name', 'notifications.manage');
            });
        });

        if ($tenantContext && !$tenantContext->isPlatform()) {
            $admins->where(function ($query) use ($tenantContext) {
                $query->whereHas('tenantMemberships', function ($membershipQuery) use ($tenantContext) {
                    $membershipQuery->where('status', 'active')
                        ->where(function ($scopeQuery) use ($tenantContext) {
                            $scopeQuery->where(function ($platform) {
                                $platform->where('scope_type', 'platform');
                            })->orWhere(function ($province) use ($tenantContext) {
                                $province->where('scope_type', 'province')
                                    ->where('scope_id', $tenantContext->provinceId);
                            })->orWhere(function ($barangay) use ($tenantContext) {
                                if ($tenantContext->barangayId) {
                                    $barangay->where('scope_type', 'barangay')
                                        ->where('scope_id', $tenantContext->barangayId);
                                }
                            });
                        });
                });
            });
        }

        $admins = $admins->get();

        foreach ($admins as $admin) {
            $this->notify($admin, 'low_stock', [
                'product_id' => $productId,
                'product_name' => $productName,
                'available' => $available,
                'threshold' => $threshold,
            ], $tenantContext);
        }
    }
}
