<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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
        return match ($type) {
            'low_stock' => "Low stock alert: {$data['product_name']} is below threshold ({$data['available']} available, threshold: {$data['threshold']}).",
            'approval_needed' => "A new request #{$data['request_id']} requires your approval.",
            'hold_expiry' => "Hold #{$data['hold_id']} is expiring soon.",
            'request_status' => "Request #{$data['request_id']} status changed to {$data['status']}.",
            default => "GTIMS Notification: " . json_encode($data),
        };
    }

    /**
     * Notify all admins about low stock.
     */
    public function notifyLowStock(int $productId, string $productName, int $available, int $threshold): void
    {
        $admins = User::whereIn('user_level_id', [1, 2])->get();
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
