<?php

namespace App\Listeners;

use App\Models\HistoryLog;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $ip = request()->ip();
        $agent = request()->userAgent();

        $description = <<<DESC
User {$user->name} logged in.
IP: {$ip}
Browser: {$agent}
DESC;

        HistoryLog::create([
            'action' => 'LOGIN SUCCESS',
            'description' => $description,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'metadata' => [
                'ip' => $ip,
                'agent' => $agent,
            ],
        ]);
    }
}
