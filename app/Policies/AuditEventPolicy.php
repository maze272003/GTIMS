<?php

namespace App\Policies;

class AuditEventPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'audit.view',
            'create' => 'audit.view',
            'update' => 'audit.view',
            'delete' => 'audit.view',
            'export' => 'reports.export',
        ];
    }
}

