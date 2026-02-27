<?php

namespace App\Services;

use App\Models\TenantUsage;
use App\Tenancy\TenantContext;

class TenantUsageService
{
    public function increment(TenantContext $ctx, string $metric, int $count = 1): void
    {
        TenantUsage::updateOrCreate(
            [
                'province_id' => $ctx->provinceId,
                'barangay_id' => $ctx->barangayId,
                'metric_key' => $metric,
                'period_start' => now()->startOfMonth()->toDateString(),
            ],
            ['period_end' => now()->endOfMonth()->toDateString()]
        );

        TenantUsage::where('province_id', $ctx->provinceId)
            ->where('barangay_id', $ctx->barangayId)
            ->where('metric_key', $metric)
            ->where('period_start', now()->startOfMonth()->toDateString())
            ->increment('metric_value', $count);
    }

    public function getCurrentUsage(TenantContext $ctx, string $metric): int
    {
        return (int) TenantUsage::where('province_id', $ctx->provinceId)
            ->where('barangay_id', $ctx->barangayId)
            ->where('metric_key', $metric)
            ->where('period_start', now()->startOfMonth()->toDateString())
            ->value('metric_value') ?? 0;
    }

    public function getQuotaLimit(TenantContext $ctx, string $metric): int
    {
        $scopeType = $ctx->isBarangay() ? 'barangay' : 'province';
        return (int) config("tenancy.quotas.{$scopeType}.{$metric}", PHP_INT_MAX);
    }

    public function isOverQuota(TenantContext $ctx, string $metric): bool
    {
        return $this->getCurrentUsage($ctx, $metric) >= $this->getQuotaLimit($ctx, $metric);
    }
}
