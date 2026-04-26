<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_edges', function (Blueprint $table) {
            $table->unique(
                ['workflow_version_id', 'source_node_id', 'target_node_id'],
                'wf_edges_version_source_target_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('workflow_edges', function (Blueprint $table) {
            $table->dropUnique('wf_edges_version_source_target_uq');
        });
    }
};

