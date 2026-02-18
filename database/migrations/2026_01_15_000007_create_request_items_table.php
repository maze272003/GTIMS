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
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incoming_request_id')->constrained('incoming_requests')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity_requested');
            $table->integer('quantity_fulfilled')->default(0);
            $table->boolean('allow_substitution')->default(false);
            $table->foreignId('substituted_product_id')->nullable()->constrained('products');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
