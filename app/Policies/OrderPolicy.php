<?php

namespace App\Policies;

class OrderPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'orders.view',
            'create' => 'orders.create',
            'update' => 'orders.approve_admin',
            'delete' => 'orders.approve_admin',
            'export' => 'reports.export',
        ];
    }
}

