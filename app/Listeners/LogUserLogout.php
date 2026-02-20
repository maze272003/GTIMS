<?php

namespace App\Listeners;

use App\Models\HistoryLog;
use App\Services\SystemActivityNotificationService;
use Illuminate\Auth\Events\Logout;

class LogUserLogout
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
    public function handle(Logout $event): void
    {
        $user = $event->user;
        if (!$user) {
            return;
        }

        $ip = request()->ip();
        $agent = request()->userAgent();

        $description = <<<DESC
User {$user->name} has logged out.
IP: {$ip}
Browser: {$agent}
DESC;

        HistoryLog::create([
            'action' => 'USER LOGOUT',
            'description' => $description,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'metadata' => [
                'ip' => $ip,
                'agent' => $agent,
            ],
        ]);

        $this->notificationService->notify([
            'type' => 'security',
            'category' => 'security',
            'action_type' => 'logout',
            'title' => 'User logout',
            'details' => [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'ip' => $ip,
                'agent' => $agent,
            ],
        ]);
    }
}
