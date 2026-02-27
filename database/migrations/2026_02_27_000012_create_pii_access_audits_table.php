<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pii_access_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('resource_type', 100);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('action', 80);
            $table->json('metadata')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->index(['province_id', 'barangay_id']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['action', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pii_access_audits');
    }
};

