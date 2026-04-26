<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_items', function (Blueprint $table): void {
            $table->index(['incoming_request_id', 'product_id'], 'request_items_request_product_idx');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->index(['order_id', 'product_id'], 'order_items_order_product_idx');
        });

        Schema::table('workflow_runs', function (Blueprint $table): void {
            $table->index(['workflow_definition_id', 'status', 'created_at'], 'workflow_runs_def_status_created_idx');
        });

        Schema::table('workflow_run_steps', function (Blueprint $table): void {
            $table->index(['workflow_run_id', 'status'], 'workflow_run_steps_run_status_idx');
        });

        Schema::table('hold_items', function (Blueprint $table): void {
            $table->index(['product_id', 'hold_id'], 'hold_items_product_hold_idx');
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->index(['entity_type', 'entity_id', 'created_at'], 'audit_events_entity_created_idx');
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->index(['product_id', 'branch_id', 'is_archived'], 'inventories_product_branch_archived_idx');
        });

        Schema::table('product_substitutes', function (Blueprint $table): void {
            $table->index(['product_id', 'substitute_product_id'], 'product_substitutes_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table): void {
            $table->dropIndex('request_items_request_product_idx');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_order_product_idx');
        });

        Schema::table('workflow_runs', function (Blueprint $table): void {
            $table->dropIndex('workflow_runs_def_status_created_idx');
        });

        Schema::table('workflow_run_steps', function (Blueprint $table): void {
            $table->dropIndex('workflow_run_steps_run_status_idx');
        });

        Schema::table('hold_items', function (Blueprint $table): void {
            $table->dropIndex('hold_items_product_hold_idx');
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex('audit_events_entity_created_idx');
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropIndex('inventories_product_branch_archived_idx');
        });

        Schema::table('product_substitutes', function (Blueprint $table): void {
            $table->dropIndex('product_substitutes_product_idx');
        });
    }
};
