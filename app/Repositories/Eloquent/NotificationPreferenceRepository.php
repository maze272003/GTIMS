<?php

namespace App\Repositories\Eloquent;

use App\Models\NotificationPreference;
use App\Repositories\Interfaces\NotificationPreferenceRepositoryInterface;

class NotificationPreferenceRepository extends BaseRepository implements NotificationPreferenceRepositoryInterface
{
    public function __construct(NotificationPreference $model)
    {
        parent::__construct($model);
    }

    public function getUserPreferencesMap(int $userId): array
    {
        return $this->model->where('user_id', $userId)
            ->get()
            ->keyBy('type')
            ->map(fn ($row) => [
                'email_enabled' => (bool) $row->email_enabled,
                'in_app_enabled' => (bool) $row->in_app_enabled,
            ])
            ->toArray();
    }

    public function upsertUserPreferences(int $userId, array $notificationTypes, array $input): void
    {
        foreach ($notificationTypes as $type) {
            $emailEnabled = (bool) data_get($input, "{$type}.email_enabled", false);
            $inAppEnabled = (bool) data_get($input, "{$type}.in_app_enabled", true);

            NotificationPreference::updateOrCreate(
                ['user_id' => $userId, 'type' => $type],
                [
                    'email_enabled' => $emailEnabled,
                    'in_app_enabled' => $inAppEnabled,
                ]
            );
        }
    }
}

