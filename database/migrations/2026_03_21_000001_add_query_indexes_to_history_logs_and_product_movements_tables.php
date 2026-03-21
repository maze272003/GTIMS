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
        Schema::table('history_logs', function (Blueprint $table) {
            $table->index('created_at', 'history_logs_created_at_idx');
            $table->index(['action', 'created_at'], 'history_logs_action_created_at_idx');
            $table->index(['user_name', 'created_at'], 'history_logs_user_name_created_at_idx');
        });

        Schema::table('product_movements', function (Blueprint $table) {
            $table->index('created_at', 'product_movements_created_at_idx');
            $table->index(['type', 'created_at'], 'product_movements_type_created_at_idx');
            $table->index(['product_id', 'created_at'], 'product_movements_product_created_at_idx');
            $table->index(['user_id', 'created_at'], 'product_movements_user_created_at_idx');
            $table->index(['inventory_id', 'created_at'], 'product_movements_inventory_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_movements', function (Blueprint $table) {
            $table->dropIndex('product_movements_created_at_idx');
            $table->dropIndex('product_movements_type_created_at_idx');
            $table->dropIndex('product_movements_product_created_at_idx');
            $table->dropIndex('product_movements_user_created_at_idx');
            $table->dropIndex('product_movements_inventory_created_at_idx');
        });

        Schema::table('history_logs', function (Blueprint $table) {
            $table->dropIndex('history_logs_created_at_idx');
            $table->dropIndex('history_logs_action_created_at_idx');
            $table->dropIndex('history_logs_user_name_created_at_idx');
        });
    }
};
