<?php

namespace App\Policies;

class SupplierPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'suppliers.view',
            'create' => 'suppliers.manage',
            'update' => 'suppliers.manage',
            'delete' => 'suppliers.manage',
            'export' => 'reports.export',
        ];
    }
}

