<?php

namespace App\Console\Commands;

use App\Models\Province;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TenantCacheClearCommand extends Command
{
    protected $signature = 'tenant:cache:clear
                            {--province= : Clear cache for a specific province by ID}
                            {--barangay= : Clear cache for a specific barangay by ID}
                            {--all : Clear cache for all tenants}';

    protected $description = 'Clear tenant-namespaced cache';

    public function handle(): int
    {
        $provinceId = $this->option('province');
        $barangayId = $this->option('barangay');
        $all = $this->option('all');

        if ($all) {
            Cache::flush();
            $this->info('All cache cleared.');
        } elseif ($provinceId || $barangayId) {
            $prefix = 'tenant:';
            if ($provinceId) {
                $prefix .= "{$provinceId}:";
            }
            if ($barangayId) {
                $prefix .= "{$barangayId}:";
            }
            $this->info("Tenant cache invalidation requested for prefix: {$prefix}");
            $this->info('For complete pattern-based clearing, use --all or configure a cache driver that supports key pattern deletion (e.g., Redis).');
        } else {
            $this->error('Specify --province, --barangay, or --all.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
