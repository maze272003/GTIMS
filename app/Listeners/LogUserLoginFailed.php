<?php

namespace App\Listeners;

use App\Models\HistoryLog;
use App\Services\SystemActivityNotificationService;
use Illuminate\Auth\Events\Failed;

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
            ],
        ]);
    }
}
