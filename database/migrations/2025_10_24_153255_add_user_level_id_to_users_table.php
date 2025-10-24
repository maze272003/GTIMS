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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_level_id')
                ->nullable()
                ->after('id') // Para maayos tingnan sa DB
                ->constrained('user_levels') // Foreign key sa 'user_levels' table
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_level_id']);
            $table->dropColumn('user_level_id');
        });
    }
};
