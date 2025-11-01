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
        Schema::create('dispensedmedications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patientrecord_id')->constrained('patientrecords')->onDelete('cascade');
            $table->foreignId('barangay_id')->index();
            $table->string('batch_number');
            $table->string('generic_name');
            $table->string('brand_name');
            $table->string('strength');
            $table->string('form');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispensedmedications');
    }
};