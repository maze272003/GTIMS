<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Record an audit event.
     */
    public function record(string $action, string $entityType, int $entityId, int $userId, ?array $before = null, ?array $after = null, ?string $reason = null, ?array $metadata = null): AuditEvent
    {
        /** @var TenantContext|null $tenantContext */
        $tenantContext = app()->bound(TenantContext::class) ? app(TenantContext::class) : null;

        $metadata = array_merge($metadata ?? [], [
            'tenant_scope' => $tenantContext?->scopeType,
            'tenant_province_id' => $tenantContext?->provinceId,
            'tenant_barangay_id' => $tenantContext?->barangayId,
            'tenant_province_slug' => $tenantContext?->provinceSlug,
            'tenant_barangay_slug' => $tenantContext?->barangaySlug,
        ]);

        $event = AuditEvent::create([
            'province_id' => $tenantContext?->provinceId,
            'barangay_id' => $tenantContext?->barangayId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);

        Log::channel('daily')->info("Audit: {$action}", [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'tenant_scope' => $tenantContext?->scopeType,
            'province_id' => $tenantContext?->provinceId,
            'barangay_id' => $tenantContext?->barangayId,
        ]);

        return $event;
    }
}
