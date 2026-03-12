<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemActivityNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SystemActivityNotificationService
{
    private ?Collection $notifiableUsers = null;

    /**
     * Send a system activity notification to users who can manage notifications.
     *
     * @param  array<string, mixed>  $payload
     */
    public function notify(array $payload): void
    {
        $users = $this->getNotifiableUsers();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            try {
                $user->notify(new SystemActivityNotification($payload));
            } catch (\Throwable $e) {
                Log::warning('Failed to store system activity notification.', [
                    'recipient_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getNotifiableUsers(): Collection
    {
        if ($this->notifiableUsers !== null) {
            return $this->notifiableUsers;
        }

        $this->notifiableUsers = User::query()
            ->whereHasPermission('notifications.manage')
            ->select(['id', 'name', 'email'])
            ->get();

        return $this->notifiableUsers;
    }
}
