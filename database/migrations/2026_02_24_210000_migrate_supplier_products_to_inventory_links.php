<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_products')) {
            return;
        }

        if (Schema::hasColumn('supplier_products', 'inventory_id') && !Schema::hasColumn('supplier_products', 'product_id')) {
            return;
        }

        Schema::dropIfExists('supplier_products_new');
        Schema::create('supplier_products_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
            $table->integer('lead_time_days')->default(7);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'inventory_id']);
        });

        if (Schema::hasColumn('supplier_products', 'product_id')) {
            DB::table('supplier_products_new')->insertUsing(
                ['supplier_id', 'inventory_id', 'lead_time_days', 'unit_cost', 'created_at', 'updated_at'],
                DB::table('supplier_products as sp')
                    ->join('inventories as i', 'i.product_id', '=', 'sp.product_id')
                    ->select(
                        'sp.supplier_id',
                        DB::raw('i.id as inventory_id'),
                        'sp.lead_time_days',
                        'sp.unit_cost',
                        'sp.created_at',
                        'sp.updated_at'
                    )
            );
        } elseif (Schema::hasColumn('supplier_products', 'inventory_id')) {
            DB::table('supplier_products_new')->insertUsing(
                ['supplier_id', 'inventory_id', 'lead_time_days', 'unit_cost', 'created_at', 'updated_at'],
                DB::table('supplier_products')
                    ->select(
                        'supplier_id',
                        'inventory_id',
                        'lead_time_days',
                        'unit_cost',
                        'created_at',
                        'updated_at'
                    )
            );
        }

        Schema::drop('supplier_products');
        Schema::rename('supplier_products_new', 'supplier_products');
    }

    public function down(): void
    {
        if (!Schema::hasTable('supplier_products')) {
            return;
        }

        if (Schema::hasColumn('supplier_products', 'product_id') && !Schema::hasColumn('supplier_products', 'inventory_id')) {
            return;
        }

        Schema::dropIfExists('supplier_products_old');
        Schema::create('supplier_products_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('lead_time_days')->default(7);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'product_id']);
        });

        if (Schema::hasColumn('supplier_products', 'inventory_id')) {
            DB::table('supplier_products_old')->insertUsing(
                ['supplier_id', 'product_id', 'lead_time_days', 'unit_cost', 'created_at', 'updated_at'],
                DB::table('supplier_products as sp')
                    ->join('inventories as i', 'i.id', '=', 'sp.inventory_id')
                    ->selectRaw(
                        'sp.supplier_id, i.product_id, MIN(sp.lead_time_days) as lead_time_days, MIN(sp.unit_cost) as unit_cost, MIN(sp.created_at) as created_at, MAX(sp.updated_at) as updated_at'
                    )
                    ->groupBy('sp.supplier_id', 'i.product_id')
            );
        }

        Schema::drop('supplier_products');
        Schema::rename('supplier_products_old', 'supplier_products');
    }
};
