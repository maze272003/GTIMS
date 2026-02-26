<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need reporting index: (province_id, created_at)
     */
    protected array $reportingTables = [
        'inventories',
        'orders',
        'patientrecords',
        'incoming_requests',
        'holds',
        'notifications',
        'audit_events',
        'history_logs',
        'product_movements',
    ];

    /**
     * Tables that need operational index: (barangay_id, created_at)
     */
    protected array $operationalTables = [
        'inventories',
        'orders',
        'patientrecords',
        'incoming_requests',
        'holds',
    ];

    /**
     * Tables missed by 000006 migration that need tenant columns added.
     */
    protected array $missedTenantTables = [
        'users',
        'branches',
        'order_items',
        'hold_items',
        'request_items',
        'request_comments',
        'request_attachments',
        'supplier_products',
        'low_stock_settings',
        'reorder_rules',
        'idempotency_keys',
    ];

    public function up(): void
    {
        // 1. Add tenant columns to tables missed by 000006
        foreach ($this->missedTenantTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'province_id')) {
                    $table->unsignedBigInteger('province_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn($tableName, 'barangay_id')) {
                    $table->unsignedBigInteger('barangay_id')->nullable()->after(
                        Schema::hasColumn($tableName, 'province_id') ? 'province_id' : 'id'
                    );
                }
            });

            // Add composite index for tenant isolation
            if (Schema::hasColumn($tableName, 'province_id') && Schema::hasColumn($tableName, 'barangay_id')) {
                try {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->index(['province_id', 'barangay_id']);
                    });
                } catch (\Throwable) {
                    // Index may already exist
                }
            }
        }

        // 2. Reporting indexes: (province_id, created_at)
        foreach ($this->reportingTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            if (!Schema::hasColumn($tableName, 'province_id') || !Schema::hasColumn($tableName, 'created_at')) {
                continue;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->index(['province_id', 'created_at'], "{$tableName}_province_date_index");
                });
            } catch (\Throwable) {
                // Index may already exist
            }
        }

        // 3. Operational indexes: (barangay_id, created_at)
        foreach ($this->operationalTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            if (!Schema::hasColumn($tableName, 'barangay_id') || !Schema::hasColumn($tableName, 'created_at')) {
                continue;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->index(['barangay_id', 'created_at'], "{$tableName}_barangay_date_index");
                });
            } catch (\Throwable) {
                // Index may already exist
            }
        }

        // 4. Module-specific composite indexes
        // inventories(province_id, barangay_id, product_id)
        if (Schema::hasTable('inventories')
            && Schema::hasColumn('inventories', 'province_id')
            && Schema::hasColumn('inventories', 'barangay_id')
            && Schema::hasColumn('inventories', 'product_id')
        ) {
            try {
                Schema::table('inventories', function (Blueprint $table) {
                    $table->index(
                        ['province_id', 'barangay_id', 'product_id'],
                        'inventories_tenant_product_index'
                    );
                });
            } catch (\Throwable) {
                // Index may already exist
            }
        }

        // patientrecords(province_id, barangay_id, date_dispensed)
        if (Schema::hasTable('patientrecords')
            && Schema::hasColumn('patientrecords', 'province_id')
            && Schema::hasColumn('patientrecords', 'barangay_id')
            && Schema::hasColumn('patientrecords', 'date_dispensed')
        ) {
            try {
                Schema::table('patientrecords', function (Blueprint $table) {
                    $table->index(
                        ['province_id', 'barangay_id', 'date_dispensed'],
                        'patientrecords_tenant_date_index'
                    );
                });
            } catch (\Throwable) {
                // Index may already exist
            }
        }

        // incoming_requests(province_id, barangay_id, status, created_at)
        if (Schema::hasTable('incoming_requests')
            && Schema::hasColumn('incoming_requests', 'province_id')
            && Schema::hasColumn('incoming_requests', 'barangay_id')
            && Schema::hasColumn('incoming_requests', 'status')
            && Schema::hasColumn('incoming_requests', 'created_at')
        ) {
            try {
                Schema::table('incoming_requests', function (Blueprint $table) {
                    $table->index(
                        ['province_id', 'barangay_id', 'status', 'created_at'],
                        'incoming_requests_tenant_status_index'
                    );
                });
            } catch (\Throwable) {
                // Index may already exist
            }
        }
    }

    public function down(): void
    {
        // 1. Drop module-specific indexes
        $moduleIndexes = [
            'incoming_requests' => 'incoming_requests_tenant_status_index',
            'patientrecords' => 'patientrecords_tenant_date_index',
            'inventories' => 'inventories_tenant_product_index',
        ];

        foreach ($moduleIndexes as $tableName => $indexName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            } catch (\Throwable) {
                // Index may not exist
            }
        }

        // 2. Drop operational indexes
        foreach ($this->operationalTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropIndex("{$tableName}_barangay_date_index");
                });
            } catch (\Throwable) {
                // Index may not exist
            }
        }

        // 3. Drop reporting indexes
        foreach ($this->reportingTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropIndex("{$tableName}_province_date_index");
                });
            } catch (\Throwable) {
                // Index may not exist
            }
        }

        // 4. Drop tenant columns from missed tables
        foreach ($this->missedTenantTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Drop composite index first
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropIndex(['province_id', 'barangay_id']);
                });
            } catch (\Throwable) {
                // Index may not exist
            }

            $dropColumns = [];

            if (Schema::hasColumn($tableName, 'province_id')) {
                $dropColumns[] = 'province_id';
            }
            if (Schema::hasColumn($tableName, 'barangay_id')) {
                $dropColumns[] = 'barangay_id';
            }

            if (!empty($dropColumns)) {
                Schema::table($tableName, function (Blueprint $table) use ($dropColumns) {
                    $table->dropColumn($dropColumns);
                });
            }
        }
    }
};
