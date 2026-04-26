<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('uses_custom_permissions')->default(false);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        $timestamp = now();

        DB::table('users')
            ->select(['id', 'user_level_id'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($timestamp) {
                foreach ($users as $user) {
                    $permissionIds = DB::table('role_permissions')
                        ->where('user_level_id', $user->user_level_id)
                        ->pluck('permission_id')
                        ->all();

                    if (!empty($permissionIds)) {
                        DB::table('user_permissions')->insertOrIgnore(
                            array_map(
                                fn ($permissionId) => [
                                    'user_id' => $user->id,
                                    'permission_id' => $permissionId,
                                    'created_at' => $timestamp,
                                    'updated_at' => $timestamp,
                                ],
                                $permissionIds
                            )
                        );
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['uses_custom_permissions' => true]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uses_custom_permissions');
        });
    }
};
