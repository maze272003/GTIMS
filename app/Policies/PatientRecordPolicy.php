<?php

namespace App\Policies;

class PatientRecordPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'patients.view',
            'create' => 'patients.manage',
            'update' => 'patients.manage',
            'delete' => 'patients.manage',
            'export' => 'reports.export',
        ];
    }
}

