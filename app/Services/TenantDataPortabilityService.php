<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantDataPortabilityService
{
    protected array $tables = [
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
        'notifications',
        'audit_events',
        'history_logs',
    ];

    public function exportTenantData(TenantContext $tenantContext, array $options = []): string
    {
        $data = [
            'tenant' => [
                'scope_type' => $tenantContext->scopeType,
                'province_id' => $tenantContext->provinceId,
                'barangay_id' => $tenantContext->barangayId,
                'province_slug' => $tenantContext->provinceSlug,
                'barangay_slug' => $tenantContext->barangaySlug,
            ],
            'generated_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        foreach ($this->tables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $query = DB::table($table);
            if (DB::getSchemaBuilder()->hasColumn($table, 'province_id')) {
                $query->where('province_id', $tenantContext->provinceId);
                if ($tenantContext->isBarangay() && DB::getSchemaBuilder()->hasColumn($table, 'barangay_id')) {
                    $query->where('barangay_id', $tenantContext->barangayId);
                }
            }

            $data['tables'][$table] = $query->get()->map(fn ($row) => (array) $row)->all();
        }

        $disk = (string) config('tenancy.storage.disk', 'local');
        $file = "tenants/province-{$tenantContext->provinceId}/exports/tenant-export-".now()->format('Ymd_His').'.json';
        Storage::disk($disk)->put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $file;
    }

    public function importTenantData(TenantContext $tenantContext, string $filePath): void
    {
        $disk = (string) config('tenancy.storage.disk', 'local');
        $raw = Storage::disk($disk)->get($filePath);
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $tables = (array) ($payload['tables'] ?? []);

        DB::transaction(function () use ($tables, $tenantContext) {
            foreach ($tables as $table => $rows) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                foreach ((array) $rows as $row) {
                    if (DB::getSchemaBuilder()->hasColumn($table, 'province_id')) {
                        $row['province_id'] = $tenantContext->provinceId;
                    }
                    if (DB::getSchemaBuilder()->hasColumn($table, 'barangay_id')) {
                        $row['barangay_id'] = $tenantContext->barangayId ?? null;
                    }

                    DB::table($table)->insert($row);
                }
            }
        });
    }
}
