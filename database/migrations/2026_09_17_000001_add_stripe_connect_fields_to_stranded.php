<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real money movement for Stranded.
 *
 * Two things were fake until now: the reward a customer offers is never
 * actually held on their card (escrow_status = 'held' was a bare flag with no
 * Stripe authorization behind it), and releaseEscrow() never called Stripe --
 * it just wrote a ledger row. This migration adds what both sides of a real
 * payout need.
 *
 * reward_hold_transaction_id mirrors escrow_transaction_id/
 * help_request_fee_transaction_id exactly: it points at
 * urban_goodz_payment_transactions.id (a bigint), never at a Stripe string id
 * -- the provider's own id belongs on that ledger row's provider_payment_id.
 *
 * A Stranded responder has never had anywhere to receive money. Express is a
 * Stripe-hosted onboarding flow, so this only needs to track the account id
 * and what Stripe reports back about it via account.updated -- the onboarding
 * UI itself lives entirely on Stripe's side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id', 60)->nullable()->after('active_request_id');
                // not_started -> pending -> active, or restricted if Stripe
                // flags the account after the fact.
                $table->string('stripe_onboarding_status', 20)->default('not_started')->after('stripe_connect_account_id');
                $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_onboarding_status');
                $table->boolean('stripe_payouts_enabled')->default(false)->after('stripe_charges_enabled');
                $table->timestamp('stripe_details_submitted_at')->nullable()->after('stripe_payouts_enabled');

                $table->index(['stripe_payouts_enabled'], 'ug_st_resp_payouts_enabled_idx');
            }
        });

        Schema::table('urban_goodz_stranded_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_requests', 'reward_hold_transaction_id')) {
                $table->unsignedBigInteger('reward_hold_transaction_id')->nullable()->after('reward_offer_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_stranded_requests', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_stranded_requests', 'reward_hold_transaction_id')) {
                $table->dropColumn('reward_hold_transaction_id');
            }
        });

        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_stranded_responders', 'stripe_connect_account_id')) {
                $table->dropIndex('ug_st_resp_payouts_enabled_idx');
                $table->dropColumn([
                    'stripe_connect_account_id',
                    'stripe_onboarding_status',
                    'stripe_charges_enabled',
                    'stripe_payouts_enabled',
                    'stripe_details_submitted_at',
                ]);
            }
        });
    }
};
