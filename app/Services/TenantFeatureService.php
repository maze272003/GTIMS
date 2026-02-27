<?php

namespace App\Services;

use App\Models\TenantFeature;
use App\Tenancy\TenantContext;

class TenantFeatureService
{
    public function isEnabled(TenantContext $ctx, string $feature): bool
    {
        $flag = TenantFeature::where('feature_key', $feature)
            ->where(function ($q) use ($ctx) {
                $q->where(function ($q2) use ($ctx) {
                    if ($ctx->barangayId) {
                        $q2->where('barangay_id', $ctx->barangayId);
                    }
                })->orWhere(function ($q2) use ($ctx) {
                    if ($ctx->provinceId) {
                        $q2->where('province_id', $ctx->provinceId)
                           ->whereNull('barangay_id');
                    }
                })->orWhere(function ($q2) {
                    $q2->whereNull('province_id')->whereNull('barangay_id');
                });
            })
            // Precedence: barangay-specific > province-level > platform-wide (global)
            ->orderByRaw('barangay_id IS NULL, province_id IS NULL')
            ->first();

        return $flag?->enabled ?? config("tenancy.features.defaults.{$feature}", false);
    }

    public function enable(TenantContext $ctx, string $feature, array $settings = []): TenantFeature
    {
        return TenantFeature::updateOrCreate(
            [
                'province_id' => $ctx->provinceId,
                'barangay_id' => $ctx->barangayId,
                'feature_key' => $feature,
            ],
            [
                'enabled' => true,
                'settings_json' => !empty($settings) ? $settings : null,
            ]
        );
    }

    public function disable(TenantContext $ctx, string $feature): bool
    {
        return TenantFeature::where('province_id', $ctx->provinceId)
            ->where('barangay_id', $ctx->barangayId)
            ->where('feature_key', $feature)
            ->update(['enabled' => false]) > 0;
    }
}
