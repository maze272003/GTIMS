<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('source_branch_id')
                ->nullable()
                ->after('product_id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('source_inventory_id')
                ->nullable()
                ->after('source_branch_id')
                ->constrained('inventories')
                ->nullOnDelete();

            $table->string('source_batch_number')
                ->nullable()
                ->after('source_inventory_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_inventory_id');
            $table->dropConstrainedForeignId('source_branch_id');
            $table->dropColumn('source_batch_number');
        });
    }
};
