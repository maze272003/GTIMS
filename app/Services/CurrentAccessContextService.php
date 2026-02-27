<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\Province;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Auth;

class CurrentAccessContextService
{
    public function __construct(
        protected TenantResolver $tenantResolver,
    ) {
    }

    public function build(): array
    {
        $user = Auth::user();
        /** @var TenantContext|null $tenantContext */
        $tenantContext = app()->bound(TenantContext::class)
            ? app(TenantContext::class)
            : $this->tenantResolver->fromSession();

        if (!$user) {
            return [
                'mode' => 'guest',
                'tenant' => null,
                'available_tenants' => [],
            ];
        }

        $mode = $tenantContext ? 'tenant' : ($user->isModerator() ? 'moderator' : 'legacy');

        return [
            'mode' => $mode,
            'tenant' => $tenantContext ? [
                'scope_type' => $tenantContext->scopeType,
                'province_id' => $tenantContext->provinceId,
                'barangay_id' => $tenantContext->barangayId,
                'province_slug' => $tenantContext->provinceSlug,
                'barangay_slug' => $tenantContext->barangaySlug,
            ] : null,
            'available_tenants' => $user->isModerator() ? $this->moderatorTenantOptions() : [],
        ];
    }

    protected function moderatorTenantOptions(): array
    {
        return Province::query()
            ->where('is_active', true)
            ->with(['barangays' => function ($query) {
                $query->where('is_active', true)->orderBy('barangay_name');
            }])
            ->orderBy('name')
            ->get()
            ->map(function (Province $province): array {
                return [
                    'province_id' => $province->id,
                    'province_name' => $province->name,
                    'province_slug' => $province->slug,
                    'barangays' => $province->barangays->map(fn (Barangay $barangay): array => [
                        'barangay_id' => $barangay->id,
                        'barangay_name' => $barangay->barangay_name,
                        'barangay_slug' => $barangay->slug,
                    ])->all(),
                ];
            })
            ->all();
    }
}

