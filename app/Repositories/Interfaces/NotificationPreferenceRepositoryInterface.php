<?php

namespace App\Repositories\Interfaces;

interface NotificationPreferenceRepositoryInterface extends RepositoryInterface
{
    public function getUserPreferencesMap(int $userId): array;

    public function upsertUserPreferences(int $userId, array $notificationTypes, array $input): void;
}

