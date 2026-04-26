<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Holds table - used in expireHolds() query: whereIn('status', ['pending', 'approved'])->where('expires_at', '<=', now())
        Schema::table('holds', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'holds_status_expires_at_idx');
        });

        // Incoming requests - used in dashboard queries filtering by status and date
        Schema::table('incoming_requests', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'incoming_requests_status_created_at_idx');
            $table->index(['branch_id', 'created_at'], 'incoming_requests_branch_created_at_idx');
        });

        // Inventories - used in expiry tracking and stock level queries
        Schema::table('inventories', function (Blueprint $table) {
            $table->index(['expiry_date', 'is_archived'], 'inventories_expiry_archived_idx');
            $table->index(['branch_id', 'is_archived', 'expiry_date'], 'inventories_branch_archived_expiry_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex('inventories_expiry_archived_idx');
            $table->dropIndex('inventories_branch_archived_expiry_idx');
        });

        Schema::table('incoming_requests', function (Blueprint $table) {
            $table->dropIndex('incoming_requests_status_created_at_idx');
            $table->dropIndex('incoming_requests_branch_created_at_idx');
        });

        Schema::table('holds', function (Blueprint $table) {
            $table->dropIndex('holds_status_expires_at_idx');
        });
    }
};
