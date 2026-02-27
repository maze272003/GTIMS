<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_route_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained('barangays')->nullOnDelete();
            $table->string('host')->nullable();
            $table->string('path_prefix')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['host', 'path_prefix']);
            $table->index(['province_id', 'barangay_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_route_bindings');
    }
};

