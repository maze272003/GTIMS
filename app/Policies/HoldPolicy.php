<?php

namespace App\Policies;

class HoldPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'holds.view',
            'create' => 'holds.create',
            'update' => 'holds.approve',
            'delete' => 'holds.release',
            'export' => 'reports.export',
        ];
    }
}

