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
        Schema::create('hold_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hold_id')->constrained('holds')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('inventory_id')->constrained('inventories');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hold_items');
    }
};
