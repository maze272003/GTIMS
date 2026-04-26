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
        Schema::table('patientrecords', function (Blueprint $table) {
            $table->index('category', 'patientrecords_category_idx');
            $table->index('date_dispensed', 'patientrecords_date_dispensed_idx');
            $table->index(['branch_id', 'date_dispensed'], 'patientrecords_branch_dispensed_idx');
            $table->index(['barangay_id', 'date_dispensed'], 'patientrecords_barangay_dispensed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patientrecords', function (Blueprint $table) {
            $table->dropIndex('patientrecords_category_idx');
            $table->dropIndex('patientrecords_date_dispensed_idx');
            $table->dropIndex('patientrecords_branch_dispensed_idx');
            $table->dropIndex('patientrecords_barangay_dispensed_idx');
        });
    }
};
