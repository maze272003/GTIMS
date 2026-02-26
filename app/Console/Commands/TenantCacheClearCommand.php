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
            // Note: Pattern-based cache clearing depends on cache driver
            // For Redis: Cache::getRedis()->del(Cache::getRedis()->keys("{$prefix}*"))
            $this->info("Tenant cache invalidation requested for prefix: {$prefix}");
            $this->warn('Note: Pattern-based clearing requires a cache driver that supports it (e.g., Redis).');
        } else {
            $this->error('Specify --province, --barangay, or --all.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
