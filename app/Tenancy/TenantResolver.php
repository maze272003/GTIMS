<?php

namespace App\Tenancy;

use App\Models\Barangay;
use App\Models\Province;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Resolves tenant context from route slugs, session data, or user membership.
 */
class TenantResolver
{
    /**
     * Resolve tenant context from province and barangay slugs.
     *
     * @return TenantContext|null Returns null if slugs don't match active records.
     */
    public function fromSlugs(string $provinceSlug, string $barangaySlug): ?TenantContext
    {
        $province = Province::where('slug', $provinceSlug)
            ->where('is_active', true)
            ->first();

        if (!$province) {
            Log::debug('Tenant resolution failed: province not found', ['slug' => $provinceSlug]);
            return null;
        }

        $barangay = Barangay::where('province_id', $province->id)
            ->where('slug', $barangaySlug)
            ->where('is_active', true)
            ->first();

        if (!$barangay) {
            Log::debug('Tenant resolution failed: barangay not found', [
                'province_slug' => $provinceSlug,
                'barangay_slug' => $barangaySlug,
            ]);
            return null;
        }

        return TenantContext::forBarangay($province, $barangay);
    }

    /**
     * Resolve tenant context for a province-only scope.
     */
    public function fromProvinceSlug(string $provinceSlug): ?TenantContext
    {
        $province = Province::where('slug', $provinceSlug)
            ->where('is_active', true)
            ->first();

        if (!$province) {
            return null;
        }

        return TenantContext::forProvince($province);
    }

    /**
     * Check if a user has an active membership for the given tenant context.
     */
    public function userHasMembership(User $user, TenantContext $ctx): bool
    {
        if ($ctx->isPlatform()) {
            return TenantMembership::where('user_id', $user->id)
                ->where('scope_type', 'platform')
                ->where('status', 'active')
                ->exists();
        }

        // Platform-scoped users (Moderators) can access any tenant
        $hasPlatformAccess = TenantMembership::where('user_id', $user->id)
            ->where('scope_type', 'platform')
            ->where('status', 'active')
            ->exists();

        if ($hasPlatformAccess) {
            return true;
        }

        if ($ctx->isProvince()) {
            return TenantMembership::where('user_id', $user->id)
                ->where('scope_type', 'province')
                ->where('scope_id', $ctx->provinceId)
                ->where('status', 'active')
                ->exists();
        }

        // Barangay scope: check barangay membership OR province membership (parent access)
        $hasProvinceAccess = TenantMembership::where('user_id', $user->id)
            ->where('scope_type', 'province')
            ->where('scope_id', $ctx->provinceId)
            ->where('status', 'active')
            ->exists();

        if ($hasProvinceAccess) {
            return true;
        }

        return TenantMembership::where('user_id', $user->id)
            ->where('scope_type', 'barangay')
            ->where('scope_id', $ctx->barangayId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Resolve context from session data.
     */
    public function fromSession(): ?TenantContext
    {
        $data = [
            'tenant.scope_type' => session('tenant.scope_type'),
            'tenant.province_id' => session('tenant.province_id'),
            'tenant.barangay_id' => session('tenant.barangay_id'),
            'tenant.route_slug_province' => session('tenant.route_slug_province'),
            'tenant.route_slug_barangay' => session('tenant.route_slug_barangay'),
        ];

        return TenantContext::fromSession($data);
    }
}
