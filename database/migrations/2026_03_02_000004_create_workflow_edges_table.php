<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('source_node_id');
            $table->string('target_node_id');
            $table->string('label')->nullable();
            $table->string('condition_branch')->nullable();
            $table->timestamps();

            $table->index('workflow_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
    }
};
