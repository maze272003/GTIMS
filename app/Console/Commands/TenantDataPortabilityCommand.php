<?php

namespace App\Console\Commands;

use App\Services\TenantDataPortabilityService;
use App\Tenancy\TenantResolver;
use Illuminate\Console\Command;

class TenantDataPortabilityCommand extends Command
{
    protected $signature = 'tenant:data-portability
                            {action : export|import}
                            {provinceSlug : Province slug}
                            {barangaySlug? : Barangay slug (optional for province scope)}
                            {--file= : File path for import action}';

    protected $description = 'Tenant export/import tooling for onboarding, migrations, and incident response.';

    public function __construct(
        protected TenantResolver $tenantResolver,
        protected TenantDataPortabilityService $portabilityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        $provinceSlug = (string) $this->argument('provinceSlug');
        $barangaySlug = $this->argument('barangaySlug');

        $tenantContext = $barangaySlug
            ? $this->tenantResolver->fromSlugs($provinceSlug, (string) $barangaySlug)
            : $this->tenantResolver->fromProvinceSlug($provinceSlug);

        if (!$tenantContext) {
            $this->error('Unable to resolve tenant context from provided slug(s).');
            return self::FAILURE;
        }

        if ($action === 'export') {
            $file = $this->portabilityService->exportTenantData($tenantContext);
            $this->info("Tenant export created: {$file}");
            return self::SUCCESS;
        }

        $file = (string) $this->option('file');
        if ($file === '') {
            $this->error('Import requires --file=<path>.');
            return self::FAILURE;
        }

        $this->portabilityService->importTenantData($tenantContext, $file);
        $this->info('Tenant import completed.');
        return self::SUCCESS;
    }
}

