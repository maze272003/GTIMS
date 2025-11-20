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
            // We use nullable() so existing users aren't broken.
            // constrained() automatically looks for a 'branches' table and 'id' column.
            // nullOnDelete() ensures if a branch is deleted, the user remains but has no branch.
            $table->foreignId('branch_id')
                  ->nullable()
                  ->after('id') 
                  ->constrained()
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['branch_id']);
            // Then drop the column
            $table->dropColumn('branch_id');
        });
    }
};