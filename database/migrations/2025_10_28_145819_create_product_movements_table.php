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
        Schema::create('product_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade'); // The specific batch
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Who caused it
            $table->enum('type', ['IN', 'OUT']); // The direction of movement
            $table->integer('quantity'); // The amount that moved (always a positive number)
            $table->integer('quantity_before'); // Stock level of the batch *before* this change
            $table->integer('quantity_after'); // Stock level of the batch *after* this change
            $table->string('description'); // e.g., "Manual stock addition", "Order fulfillment"
            $table->timestamps(); // When it happened
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_movements');
    }
};