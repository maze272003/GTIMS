<?php

namespace App\Services;

use App\Models\TenantMembership;
use App\Models\TenantSuspension;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantSuspensionService
{
    public function suspend(int $provinceId, ?int $barangayId, string $reason, string $type): TenantSuspension
    {
        return DB::transaction(function () use ($provinceId, $barangayId, $reason, $type) {
            $this->updateTenantStatus($provinceId, $barangayId, false);
            $this->suspendMemberships($provinceId, $barangayId);

            return TenantSuspension::create([
                'province_id' => $provinceId,
                'barangay_id' => $barangayId,
                'suspension_type' => $type,
                'reason' => $reason,
                'suspended_by' => Auth::id(),
                'suspended_at' => now(),
            ]);
        });
    }

    public function reactivate(TenantSuspension $suspension): TenantSuspension
    {
        return DB::transaction(function () use ($suspension) {
            $this->updateTenantStatus($suspension->province_id, $suspension->barangay_id, true);
            $this->reactivateMemberships($suspension->province_id, $suspension->barangay_id);

            $suspension->update([
                'reactivated_by' => Auth::id(),
                'reactivated_at' => now(),
            ]);

            return $suspension->fresh();
        });
    }

    protected function updateTenantStatus(int $provinceId, ?int $barangayId, bool $isActive): void
    {
        if ($barangayId) {
            \App\Models\Barangay::where('id', $barangayId)->update(['is_active' => $isActive]);
        } else {
            \App\Models\Province::where('id', $provinceId)->update(['is_active' => $isActive]);
        }
    }

    protected function suspendMemberships(int $provinceId, ?int $barangayId): void
    {
        $query = TenantMembership::where('status', 'active');
        if ($barangayId) {
            $query->where('scope_type', 'barangay')->where('scope_id', $barangayId);
        } else {
            $query->where('scope_type', 'province')->where('scope_id', $provinceId);
        }
        $query->update(['status' => 'suspended']);
    }

    protected function reactivateMemberships(int $provinceId, ?int $barangayId): void
    {
        $query = TenantMembership::where('status', 'suspended');
        if ($barangayId) {
            $query->where('scope_type', 'barangay')->where('scope_id', $barangayId);
        } else {
            $query->where('scope_type', 'province')->where('scope_id', $provinceId);
        }
        $query->update(['status' => 'active']);
    }
}
