<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need province_id and barangay_id for tenant isolation.
     * All columns are nullable during transition (Phase 2 of migration plan).
     */
    protected array $tenantTables = [
        'inventories',
        'orders',
        'patientrecords',
        'dispensedmedications',
        'holds',
        'incoming_requests',
        'suppliers',
        'notifications',
        'audit_events',
        'history_logs',
        'product_movements',
    ];

    public function up(): void
    {
        foreach ($this->tenantTables as $tableName) {
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

            // Add composite index if both columns exist and index doesn't exist yet
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
    }

    public function down(): void
    {
        foreach ($this->tenantTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $dropColumns = [];

            if (Schema::hasColumn($tableName, 'province_id')) {
                $dropColumns[] = 'province_id';
            }

            // Only drop barangay_id if we added it (not for tables that already had it)
            $tablesWithExistingBarangayId = ['patientrecords', 'dispensedmedications', 'holds'];
            if (Schema::hasColumn($tableName, 'barangay_id') && !in_array($tableName, $tablesWithExistingBarangayId)) {
                $dropColumns[] = 'barangay_id';
            }

            if (!empty($dropColumns)) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($dropColumns) {
                        $table->dropIndex(['province_id', 'barangay_id']);
                    });
                } catch (\Throwable) {
                    // Index may not exist
                }

                Schema::table($tableName, function (Blueprint $table) use ($dropColumns) {
                    $table->dropColumn($dropColumns);
                });
            }
        }
    }
};
