<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('email', 255);
            $table->unsignedBigInteger('role_id');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('invited_by');
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamps();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_suspensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('suspension_type');
            $table->text('reason');
            $table->unsignedBigInteger('suspended_by');
            $table->timestamp('suspended_at');
            $table->unsignedBigInteger('reactivated_by')->nullable();
            $table->timestamp('reactivated_at')->nullable();
            $table->timestamps();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('plan_id', 50);
            $table->string('status')->default('trial');
            $table->string('billing_cycle')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('stripe_subscription_id', 255)->nullable();
            $table->string('stripe_customer_id', 255)->nullable();
            $table->timestamps();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('event_type', 100);
            $table->string('endpoint_url', 500);
            $table->string('secret', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestamps();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('feature_key', 100);
            $table->boolean('enabled')->default(false);
            $table->json('settings_json')->nullable();
            $table->timestamps();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('metric_key', 100);
            $table->bigInteger('metric_value')->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('updated_at')->nullable();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_health', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('check_type', 50);
            $table->string('status')->default('healthy');
            $table->json('details')->nullable();
            $table->timestamp('checked_at');

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('incident_type', 100);
            $table->string('severity')->default('low');
            $table->string('status')->default('open');
            $table->text('description');
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('reported_by');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('tenant_onboarding', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->string('status')->default('pending');
            $table->string('current_step', 50)->nullable();
            $table->json('completed_steps')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->timestamp('activated_at')->nullable();

            $table->index('province_id');
        });

        Schema::create('archived_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('source_table', 100);
            $table->unsignedBigInteger('record_id');
            $table->json('archived_data');
            $table->timestamp('archived_at');
            $table->unsignedBigInteger('archived_by');
            $table->date('retention_until')->nullable();

            $table->index(['province_id', 'barangay_id']);
        });

        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('barangay_id')->nullable();
            $table->string('request_type');
            $table->string('status')->default('pending');
            $table->string('requested_by_email', 255);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['province_id', 'barangay_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_subject_requests');
        Schema::dropIfExists('archived_records');
        Schema::dropIfExists('tenant_onboarding');
        Schema::dropIfExists('tenant_incidents');
        Schema::dropIfExists('tenant_health');
        Schema::dropIfExists('tenant_usage');
        Schema::dropIfExists('tenant_features');
        Schema::dropIfExists('tenant_webhooks');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('tenant_suspensions');
        Schema::dropIfExists('tenant_invitations');
    }
};
