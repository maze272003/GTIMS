<?php

namespace App\Models\Traits;

use App\Tenancy\TenantContext;

/**
 * Provides tenant-scoping query helpers for models with province_id and barangay_id.
 */
trait TenantScoped
{
    use BelongsToProvince, BelongsToBarangay;

    /**
     * Scope queries to the given tenant context.
     *
     * - Platform scope: no filter (returns all)
     * - Province scope: filters by province_id
     * - Barangay scope: filters by province_id AND barangay_id
     */
    public function scopeForTenant($query, TenantContext $ctx)
    {
        if ($ctx->isPlatform()) {
            return $query;
        }

        $query->where($this->getTable() . '.province_id', $ctx->provinceId);

        if ($ctx->isBarangay() && $ctx->barangayId) {
            $query->where($this->getTable() . '.barangay_id', $ctx->barangayId);
        }

        return $query;
    }
}
