<?php

namespace App\Services;

use App\Models\PiiAccessAudit;
use App\Tenancy\TenantContext;

class PiiAuditService
{
    public function log(string $resourceType, string $action, ?int $resourceId = null, array $metadata = []): void
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;

        PiiAccessAudit::create([
            'user_id' => auth()->id(),
            'province_id' => $tenantContext?->provinceId,
            'barangay_id' => $tenantContext?->barangayId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'action' => $action,
            'metadata' => $metadata,
            'accessed_at' => now(),
        ]);
    }
}

