<?php

namespace App\Services;

use App\Models\TenantSubscription;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;

class BillingIntegrationService
{
    public function isEnabled(): bool
    {
        return (bool) config('tenancy.billing.enabled', false);
    }

    public function syncSubscription(TenantContext $tenantContext): ?TenantSubscription
    {
        if (!$this->isEnabled()) {
            Log::info('Billing sync skipped because billing integration is disabled.', [
                'province_id' => $tenantContext->provinceId,
                'barangay_id' => $tenantContext->barangayId,
            ]);

            return null;
        }

        return TenantSubscription::query()
            ->where('province_id', $tenantContext->provinceId)
            ->where('barangay_id', $tenantContext->barangayId)
            ->latest('id')
            ->first();
    }
}

