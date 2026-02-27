<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Province;
use App\Models\TenantHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        $dbConnected = $this->databaseConnected();
        $failedJobs = $this->failedJobsCount();
        $apiLatencyMs = $this->apiProbeLatencyMs();
        $storageUsage = $this->tenantStorageUsage($province->id, null);
        $activity24h = $this->tenantActivityCount($province->id, null);
        $errorRate = $failedJobs > 0 ? round(($failedJobs / max(1, $activity24h)) * 100, 2) : 0;

        $status = !$dbConnected
            ? 'critical'
            : ($failedJobs > 10 || $apiLatencyMs > 1000 ? 'degraded' : 'healthy');

        $details = [
            'barangay_count' => $province->barangays()->count(),
            'active_barangays' => $province->barangays()->where('is_active', true)->count(),
            'db_connected' => $dbConnected,
            'failed_jobs' => $failedJobs,
            'api_response_time_ms' => $apiLatencyMs,
            'storage_usage_bytes' => $storageUsage,
            'error_rate_percent' => $errorRate,
            'active_user_events_24h' => $activity24h,
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

        if (in_array($status, ['degraded', 'critical'], true)) {
            Log::channel('tenancy_alerts')->warning('Province health degraded.', [
                'province_id' => $province->id,
                'status' => $status,
                'details' => $details,
            ]);
        }

        $this->info("  Province '{$province->name}': {$status}");

        foreach ($province->barangays()->where('is_active', true)->get() as $barangay) {
            $this->checkBarangay($barangay);
        }
    }

    protected function checkBarangay(Barangay $barangay): void
    {
        $dbConnected = $this->databaseConnected();
        $failedJobs = $this->failedJobsCount();
        $apiLatencyMs = $this->apiProbeLatencyMs();
        $storageUsage = $this->tenantStorageUsage($barangay->province_id, $barangay->id);
        $activity24h = $this->tenantActivityCount($barangay->province_id, $barangay->id);
        $status = !$dbConnected
            ? 'critical'
            : ($failedJobs > 10 || $apiLatencyMs > 1000 ? 'degraded' : 'healthy');

        $details = [
            'province_id' => $barangay->province_id,
            'is_active' => $barangay->is_active,
            'db_connected' => $dbConnected,
            'failed_jobs' => $failedJobs,
            'api_response_time_ms' => $apiLatencyMs,
            'storage_usage_bytes' => $storageUsage,
            'active_user_events_24h' => $activity24h,
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

        if (in_array($status, ['degraded', 'critical'], true)) {
            Log::channel('tenancy_alerts')->warning('Barangay health degraded.', [
                'province_id' => $barangay->province_id,
                'barangay_id' => $barangay->id,
                'status' => $status,
                'details' => $details,
            ]);
        }

        $this->info("  Barangay '{$barangay->barangay_name}': {$status}");
    }

    protected function databaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function failedJobsCount(): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }

    protected function apiProbeLatencyMs(): float
    {
        $start = microtime(true);
        DB::table('provinces')->limit(1)->get();
        $end = microtime(true);

        return round(($end - $start) * 1000, 2);
    }

    protected function tenantStorageUsage(int $provinceId, ?int $barangayId): int
    {
        $disk = (string) config('tenancy.storage.disk', 'local');
        $base = "tenants/province-{$provinceId}";
        if ($barangayId) {
            $base .= "/barangay-{$barangayId}";
        }

        if (!Storage::disk($disk)->exists($base)) {
            return 0;
        }

        $files = Storage::disk($disk)->allFiles($base);

        return collect($files)->sum(function (string $file) use ($disk) {
            try {
                return (int) Storage::disk($disk)->size($file);
            } catch (\Throwable) {
                return 0;
            }
        });
    }

    protected function tenantActivityCount(int $provinceId, ?int $barangayId): int
    {
        if (!Schema::hasTable('audit_events')) {
            return 0;
        }

        return (int) DB::table('audit_events')
            ->where('province_id', $provinceId)
            ->when($barangayId, fn ($q) => $q->where('barangay_id', $barangayId))
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }
}
