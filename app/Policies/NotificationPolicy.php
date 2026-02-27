<?php

namespace App\Policies;

class NotificationPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'notifications.manage',
            'create' => 'notifications.manage',
            'update' => 'notifications.manage',
            'delete' => 'notifications.manage',
            'export' => 'reports.export',
        ];
    }
}

