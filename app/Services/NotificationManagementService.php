<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\NotificationPreferenceRepositoryInterface;

class NotificationManagementService
{
    public function __construct(
        protected NotificationPreferenceRepositoryInterface $notificationPreferenceRepository
    ) {
    }

    public function getIndexData(User $user): array
    {
        return [
            'notifications' => $user->notifications()->paginate(20),
            'unreadCount' => $user->unreadNotifications()->count(),
        ];
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->markAsRead();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function getPreferenceData(int $userId): array
    {
        $notificationTypes = [
            'low_stock',
            'approval_needed',
            'hold_expiry',
            'request_status',
        ];

        return [
            'notificationTypes' => $notificationTypes,
            'preferences' => $this->notificationPreferenceRepository->getUserPreferencesMap($userId),
        ];
    }

    public function updatePreferences(int $userId, array $input): void
    {
        $notificationTypes = [
            'low_stock',
            'approval_needed',
            'hold_expiry',
            'request_status',
        ];

        $this->notificationPreferenceRepository->upsertUserPreferences($userId, $notificationTypes, $input);
    }
}

