<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('id')->constrained('provinces')->nullOnDelete();
            $table->string('slug')->nullable()->after('barangay_name');
            $table->boolean('is_active')->default(true)->after('slug');
            $table->string('external_code')->nullable()->after('is_active');
            $table->json('settings_json')->nullable()->after('external_code');

            $table->unique(['province_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropUnique(['province_id', 'slug']);
            $table->dropForeign(['province_id']);
            $table->dropColumn(['province_id', 'slug', 'is_active', 'external_code', 'settings_json']);
        });
    }
};
