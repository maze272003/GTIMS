<?php

namespace App\Console\Commands;

use App\Models\Province;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantMigrationCommand extends Command
{
    protected $signature = 'tenant:migration
                            {action : inventory|mapping|backfill|monitor|reconcile|null-scan}
                            {--dry-run : Preview changes without writes}
                            {--chunk=500 : Batch size for backfill}
                            {--hours=24 : Lookback for monitor mode}';

    protected $description = 'Run tenancy migration inventory, backfill, monitoring, and reconciliation actions.';

    protected array $tenantTables = [
        'users',
        'branches',
        'inventories',
        'orders',
        'order_items',
        'patientrecords',
        'dispensedmedications',
        'holds',
        'hold_items',
        'incoming_requests',
        'request_items',
        'request_comments',
        'request_attachments',
        'suppliers',
        'supplier_products',
        'low_stock_settings',
        'reorder_rules',
        'notifications',
        'audit_events',
        'history_logs',
        'product_movements',
        'idempotency_keys',
    ];

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'inventory' => $this->inventory(),
            'mapping' => $this->mapping(),
            'backfill' => $this->backfill(),
            'monitor' => $this->monitor(),
            'reconcile' => $this->reconcile(),
            'null-scan' => $this->nullScan(),
            default => self::FAILURE,
        };
    }

    protected function inventory(): int
    {
        $rows = [];

        foreach ($this->tenantTables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $hasProvince = Schema::hasColumn($table, 'province_id');
            $hasBarangay = Schema::hasColumn($table, 'barangay_id');
            $total = (int) DB::table($table)->count();
            $nullProvince = $hasProvince ? (int) DB::table($table)->whereNull('province_id')->count() : null;
            $nullBarangay = $hasBarangay ? (int) DB::table($table)->whereNull('barangay_id')->count() : null;

            $rows[] = [
                $table,
                $hasProvince ? 'yes' : 'no',
                $hasBarangay ? 'yes' : 'no',
                $total,
                $nullProvince ?? '-',
                $nullBarangay ?? '-',
            ];
        }

        $this->table(
            ['table', 'province_id', 'barangay_id', 'rows', 'null_province', 'null_barangay'],
            $rows
        );

        return self::SUCCESS;
    }

    protected function mapping(): int
    {
        $defaultProvince = Province::query()->orderBy('id')->first();

        $this->info('Canonical branch -> province/barangay mapping report');
        $this->line('No explicit branch mapping table exists. Default mapping will use:');
        $this->line('- province_id: ' . ($defaultProvince?->id ?? 'N/A'));
        $this->line('- barangay_id: derived from existing row barangay_id when available, else NULL');
        $this->line('Recommendation: create and validate branch mapping before production backfill.');

        return self::SUCCESS;
    }

    protected function backfill(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));
        $defaultProvinceId = (int) (env('TENANCY_DEFAULT_PROVINCE_ID') ?: Province::query()->value('id'));

        if (!$defaultProvinceId) {
            $this->error('Cannot backfill without at least one province record.');
            return self::FAILURE;
        }

        foreach ($this->tenantTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'province_id')) {
                continue;
            }

            $query = DB::table($table)->whereNull('province_id');
            $pending = (int) $query->count();
            if ($pending === 0) {
                continue;
            }

            $this->line("Backfill {$table}: {$pending} rows");
            if ($dryRun) {
                continue;
            }

            $query->orderBy('id')->chunkById($chunk, function ($rows) use ($table, $defaultProvinceId) {
                foreach ($rows as $row) {
                    $provinceId = $defaultProvinceId;
                    if (isset($row->barangay_id) && $row->barangay_id) {
                        $derived = DB::table('barangays')->where('id', $row->barangay_id)->value('province_id');
                        $provinceId = $derived ?: $defaultProvinceId;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'province_id' => $provinceId,
                    ]);
                }
            });
        }

        $this->info($dryRun ? 'Dry-run backfill completed.' : 'Backfill completed.');
        return self::SUCCESS;
    }

    protected function monitor(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $this->info("Monitoring writes with missing tenant keys in last {$hours}h");

        $rows = [];
        foreach ($this->tenantTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'province_id') || !Schema::hasColumn($table, 'created_at')) {
                continue;
            }

            $count = (int) DB::table($table)
                ->whereNull('province_id')
                ->where('created_at', '>=', now()->subHours($hours))
                ->count();

            if ($count > 0) {
                $rows[] = [$table, $count];
            }
        }

        if (empty($rows)) {
            $this->info('No missing-tenant-key writes detected in monitor window.');
            return self::SUCCESS;
        }

        $this->table(['table', 'missing_writes'], $rows);
        return self::FAILURE;
    }

    protected function reconcile(): int
    {
        $rows = [];
        foreach ($this->tenantTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'province_id')) {
                continue;
            }

            $total = (int) DB::table($table)->count();
            $nulls = (int) DB::table($table)->whereNull('province_id')->count();
            $rows[] = [$table, $total, $nulls];
        }

        $this->table(['table', 'total_rows', 'null_province_rows'], $rows);
        $hasNulls = collect($rows)->contains(fn ($row) => (int) $row[2] > 0);

        return $hasNulls ? self::FAILURE : self::SUCCESS;
    }

    protected function nullScan(): int
    {
        return $this->reconcile();
    }
}

