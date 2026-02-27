<?php

namespace App\Listeners;

use App\Models\HistoryLog;
use App\Services\SystemActivityNotificationService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LogUserLoginFailed
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly SystemActivityNotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $ip = request()->ip();
        $agent = request()->userAgent();
        $email = $event->credentials['email'] ?? 'Unknown';
        $tenantKey = trim((string) request()->route('provinceSlug') . '/' . (string) request()->route('barangaySlug'), '/');
        $tenantKey = $tenantKey !== '' ? $tenantKey : 'global';

        $description = <<<DESC
Failed login attempt for email: {$email}.
IP: {$ip}
Browser: {$agent}
DESC;

        HistoryLog::create([
            'action' => 'LOGIN FAILED',
            'description' => $description,
            'user_id' => null,
            'user_name' => 'Unknown',
            'metadata' => [
                'ip' => $ip,
                'agent' => $agent,
                'tenant_scope' => $tenantKey,
            ],
        ]);

        $this->notificationService->notify([
            'type' => 'security',
            'category' => 'security',
            'action_type' => 'login_failed',
            'title' => 'Failed login attempt',
            'details' => [
                'email' => $email,
                'ip' => $ip,
                'agent' => $agent,
                'tenant_scope' => $tenantKey,
            ],
        ]);

        $attemptKey = "tenant-login-fail:{$tenantKey}:{$email}:{$ip}";
        $attempts = Cache::increment($attemptKey);
        if ($attempts === 1) {
            Cache::put($attemptKey, 1, now()->addMinutes(15));
        }

        if ($attempts >= 5) {
            Log::channel('security')->warning('Repeated login failures detected', [
                'email' => $email,
                'ip' => $ip,
                'tenant_scope' => $tenantKey,
                'attempts' => $attempts,
            ]);
        }
    }
}
