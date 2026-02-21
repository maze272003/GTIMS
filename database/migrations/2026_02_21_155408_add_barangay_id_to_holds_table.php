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
        if (Schema::hasColumn('holds', 'barangay_id')) {
            return;
        }

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
        if (!Schema::hasColumn('holds', 'barangay_id')) {
            return;
        }

        // Keep rollback safe and non-destructive for existing environments.
    }

};
