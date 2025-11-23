<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up()
{
    Schema::table('patientrecords', function (Blueprint $table) {
        $table->string('signature_path')->nullable()->after('date_dispensed');
    });
}

public function down()
{
    Schema::table('patientrecords', function (Blueprint $table) {
        $table->dropColumn('signature_path');
    });
}
};
