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
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'province_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('province_id')->nullable()->after('id');
                    $table->unsignedBigInteger('barangay_id')->nullable()->after('province_id');

                    $table->index(['province_id', 'barangay_id']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'province_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropIndex([$tableName . '_province_id_barangay_id_index']);
                    $table->dropColumn(['province_id', 'barangay_id']);
                });
            }
        }
    }
};
