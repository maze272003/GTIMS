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
        Schema::create('patientrecords', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name');
            $table->string('barangay');
            $table->string('purok');
            $table->enum('category', ['Adult', 'Child', 'Senior']);
            $table->date('date_dispensed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patientrecords');
    }
};
