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
        Schema::table('holds', function (Blueprint $table) {
            $table->foreignId('barangay_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('barangays')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('holds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
        });
    }

};
