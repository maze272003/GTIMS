<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->integer('onhand_qty')->default(0);
            $table->integer('hold_qty')->default(0);
            $table->index(['onhand_qty', 'hold_qty'], 'inventories_onhand_hold_idx');
        });

        DB::table('inventories')->update([
            'onhand_qty' => DB::raw('quantity'),
            'hold_qty' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropIndex('inventories_onhand_hold_idx');
            $table->dropColumn(['onhand_qty', 'hold_qty']);
        });
    }
};
