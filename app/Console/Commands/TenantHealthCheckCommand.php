<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Province;
use App\Models\TenantHealth;
use Illuminate\Console\Command;

class TenantHealthCheckCommand extends Command
{
    protected $signature = 'tenant:health-check
                            {--province= : Check a specific province by ID}
                            {--barangay= : Check a specific barangay by ID}';

    protected $description = 'Run health checks for tenants';

    public function handle(): int
    {
        $provinceId = $this->option('province');
        $barangayId = $this->option('barangay');

        if ($barangayId) {
            $barangay = Barangay::find($barangayId);
            if (!$barangay) {
                $this->error("Barangay #{$barangayId} not found.");
                return self::FAILURE;
            }
            $this->checkBarangay($barangay);
        } elseif ($provinceId) {
            $province = Province::find($provinceId);
            if (!$province) {
                $this->error("Province #{$provinceId} not found.");
                return self::FAILURE;
            }
            $this->checkProvince($province);
        } else {
            $this->checkAll();
        }

        $this->info('Health check complete.');
        return self::SUCCESS;
    }

    protected function checkAll(): void
    {
        $provinces = Province::where('is_active', true)->get();
        $this->info("Checking {$provinces->count()} active provinces...");

        foreach ($provinces as $province) {
            $this->checkProvince($province);
        }
    }

    protected function checkProvince(Province $province): void
    {
        $this->line("Checking province: {$province->name} (#{$province->id})");

        $status = 'healthy';
        $details = [
            'barangay_count' => $province->barangays()->count(),
            'active_barangays' => $province->barangays()->where('is_active', true)->count(),
            'checked_at' => now()->toIso8601String(),
        ];

        TenantHealth::updateOrCreate(
            [
                'province_id' => $province->id,
                'barangay_id' => null,
                'check_type' => 'province_status',
            ],
            [
                'status' => $status,
                'details' => $details,
                'checked_at' => now(),
            ]
        );

        $this->info("  Province '{$province->name}': {$status}");

        foreach ($province->barangays()->where('is_active', true)->get() as $barangay) {
            $this->checkBarangay($barangay);
        }
    }

    protected function checkBarangay(Barangay $barangay): void
    {
        $status = 'healthy';
        $details = [
            'province_id' => $barangay->province_id,
            'is_active' => $barangay->is_active,
            'checked_at' => now()->toIso8601String(),
        ];

        TenantHealth::updateOrCreate(
            [
                'province_id' => $barangay->province_id,
                'barangay_id' => $barangay->id,
                'check_type' => 'barangay_status',
            ],
            [
                'status' => $status,
                'details' => $details,
                'checked_at' => now(),
            ]
        );

        $this->info("  Barangay '{$barangay->barangay_name}': {$status}");
    }
}
