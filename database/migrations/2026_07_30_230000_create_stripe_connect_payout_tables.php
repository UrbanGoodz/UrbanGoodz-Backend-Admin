<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_connected_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_role', 40);
            $table->unsignedBigInteger('owner_id');
            $table->string('stripe_account_id')->nullable()->unique();
            $table->string('environment', 16)->default('sandbox');
            $table->string('api_version', 40)->default('2026-02-25.clover');
            $table->string('dashboard_type', 16)->default('express');
            $table->string('country', 2)->default('US');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('creating');
            $table->string('restriction_status', 32)->default('requirements_due');
            $table->string('disabled_reason')->nullable();
            $table->string('transfer_capability_status', 24)->default('pending');
            $table->string('payout_capability_status', 24)->default('pending');
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->json('requirements_currently_due')->nullable();
            $table->json('requirements_eventually_due')->nullable();
            $table->json('requirement_errors')->nullable();
            $table->bigInteger('available_balance_cents')->default(0);
            $table->bigInteger('pending_balance_cents')->default(0);
            $table->boolean('admin_payouts_enabled')->default(true);
            $table->boolean('manual_hold')->default(false);
            $table->boolean('refund_hold')->default(false);
            $table->boolean('is_suspended')->default(false);
            $table->boolean('instant_payout_eligible')->default(false);
            $table->unsignedBigInteger('minimum_payout_cents')->default(0);
            $table->unsignedSmallInteger('payout_delay_days')->default(0);
            $table->string('payout_schedule', 24)->default('daily');
            $table->timestamp('next_expected_payout_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_stripe_event_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_role', 'owner_id'], 'ug_connect_owner_unique');
            $table->index(['status', 'payouts_enabled'], 'ug_connect_status_idx');
        });

        Schema::create('urban_goodz_payout_actor_bindings', function (Blueprint $table) {
            $table->id();
            $table->string('owner_role', 40);
            $table->unsignedBigInteger('owner_id');
            $table->string('actor_type', 40);
            $table->unsignedBigInteger('actor_id');
            $table->boolean('can_manage')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
            $table->unique(
                ['owner_role', 'owner_id', 'actor_type', 'actor_id'],
                'ug_payout_actor_binding_unique'
            );
            $table->index(['actor_type', 'actor_id'], 'ug_payout_actor_idx');
        });

        Schema::create('urban_goodz_payout_role_controls', function (Blueprint $table) {
            $table->id();
            $table->string('owner_role', 40)->unique();
            $table->boolean('payouts_enabled')->default(true);
            $table->unsignedBigInteger('minimum_payout_cents')->default(0);
            $table->string('payout_schedule', 24)->default('daily');
            $table->unsignedSmallInteger('payout_delay_days')->default(0);
            $table->boolean('refund_hold')->default(false);
            $table->boolean('instant_payout_allowed')->default(false);
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('urban_goodz_settlement_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_snapshot_id');
            $table->unsignedBigInteger('connected_account_id')->nullable();
            $table->string('owner_role', 40);
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('gross_amount_cents');
            $table->unsignedBigInteger('commission_cents')->default(0);
            $table->unsignedBigInteger('admin_fee_cents')->default(0);
            $table->unsignedBigInteger('net_amount_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('pending');
            $table->timestamps();
            $table->unique(
                ['settlement_snapshot_id', 'owner_role', 'owner_id'],
                'ug_settlement_recipient_unique'
            );
            $table->index(['owner_role', 'owner_id', 'status'], 'ug_recipient_owner_idx');
        });

        Schema::create('urban_goodz_payout_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('settlement_recipient_id');
            $table->unsignedBigInteger('connected_account_id');
            $table->string('stripe_transfer_id')->nullable()->unique();
            $table->string('payment_provider', 24)->default('stripe');
            $table->string('provider_payment_id');
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('reversed_amount_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 32)->default('pending');
            $table->string('blocked_reason')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('last_stripe_event_at')->nullable();
            $table->timestamps();
            $table->index(['connected_account_id', 'status'], 'ug_transfer_account_idx');
        });

        Schema::create('urban_goodz_transfer_reversals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payout_transfer_id');
            $table->string('stripe_reversal_id')->nullable()->unique();
            $table->string('refund_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 24)->default('pending');
            $table->string('reason');
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('last_stripe_event_at')->nullable();
            $table->timestamps();
            $table->index(['payout_transfer_id', 'status'], 'ug_reversal_transfer_idx');
        });

        Schema::create('urban_goodz_connected_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connected_account_id');
            $table->string('stripe_payout_id')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status', 24);
            $table->string('method', 24)->nullable();
            $table->string('type', 24)->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('arrival_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('last_stripe_event_at')->nullable();
            $table->timestamps();
            $table->index(['connected_account_id', 'status'], 'ug_payout_account_idx');
        });

        Schema::create('urban_goodz_stripe_connect_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->unsignedBigInteger('connected_account_id')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->string('event_type');
            $table->string('object_id')->nullable();
            $table->unsignedBigInteger('stripe_created_at')->nullable();
            $table->string('payload_sha256', 64);
            $table->json('sanitized_payload')->nullable();
            $table->string('status', 24)->default('processing');
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['stripe_account_id', 'event_type'], 'ug_connect_event_account_idx');
        });

        Schema::create('urban_goodz_payout_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id'], 'ug_payout_audit_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_payout_audit_events');
        Schema::dropIfExists('urban_goodz_stripe_connect_events');
        Schema::dropIfExists('urban_goodz_connected_payouts');
        Schema::dropIfExists('urban_goodz_transfer_reversals');
        Schema::dropIfExists('urban_goodz_payout_transfers');
        Schema::dropIfExists('urban_goodz_settlement_recipients');
        Schema::dropIfExists('urban_goodz_payout_actor_bindings');
        Schema::dropIfExists('urban_goodz_payout_role_controls');
        Schema::dropIfExists('urban_goodz_connected_accounts');
    }
};
