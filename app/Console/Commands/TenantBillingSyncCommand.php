<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Province;
use App\Services\BillingIntegrationService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class TenantBillingSyncCommand extends Command
{
    protected $signature = 'tenant:billing:sync
                            {province : Province ID}
                            {barangay? : Barangay ID (optional)}';

    protected $description = 'Run billing provider sync hook for a tenant (no-op when disabled).';

    public function __construct(
        protected BillingIntegrationService $billingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $province = Province::query()->find((int) $this->argument('province'));
        if (!$province) {
            $this->error('Province not found.');
            return self::FAILURE;
        }

        $barangayId = $this->argument('barangay');
        $barangay = $barangayId ? Barangay::query()->find((int) $barangayId) : null;

        $tenantContext = $barangay
            ? TenantContext::forBarangay($province, $barangay)
            : TenantContext::forProvince($province);

        $subscription = $this->billingService->syncSubscription($tenantContext);
        if (!$subscription) {
            $this->line('No subscription sync performed (billing disabled or no subscription).');
            return self::SUCCESS;
        }

        $this->info('Billing subscription synced: #' . $subscription->id);
        return self::SUCCESS;
    }
}

