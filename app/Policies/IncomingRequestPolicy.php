<?php

namespace App\Policies;

class IncomingRequestPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'requests.view',
            'create' => 'requests.create',
            'update' => 'requests.approve',
            'delete' => 'requests.approve',
            'export' => 'reports.export',
        ];
    }
}

