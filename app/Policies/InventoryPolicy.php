<?php

namespace App\Policies;

class InventoryPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'inventory.view',
            'create' => 'inventory.add',
            'update' => 'inventory.edit',
            'delete' => 'inventory.archive',
            'export' => 'reports.export',
        ];
    }
}

