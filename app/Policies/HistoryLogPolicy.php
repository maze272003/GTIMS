<?php

namespace App\Policies;

class HistoryLogPolicy extends TenantPolicy
{
    protected function permissionMap(): array
    {
        return [
            'view' => 'historylog.view',
            'create' => 'historylog.view',
            'update' => 'historylog.view',
            'delete' => 'historylog.view',
            'export' => 'reports.export',
        ];
    }
}

