<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('node_id');
            $table->enum('type', ['trigger', 'condition', 'action']);
            $table->string('action_type');
            $table->string('label');
            $table->json('config')->nullable();
            $table->json('position')->nullable();
            $table->timestamps();

            $table->unique(['workflow_version_id', 'node_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_nodes');
    }
};
