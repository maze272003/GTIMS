<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('permission', ['view', 'edit', 'publish', 'run']);
            $table->timestamps();

            $table->unique(
                ['workflow_definition_id', 'user_id', 'permission'],
                'wf_permissions_wf_user_perm_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_permissions');
    }
};
