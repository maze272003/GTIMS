<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->index();
            $table->foreignId('branch_id')->index();
            $table->string('batch_number')->unique();
            $table->integer('quantity')->default(0);
            $table->date('expiry_date');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};