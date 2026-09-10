<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Responder payout destinations.
 *
 * Releasing escrow used to mean writing a ledger row that said the money had
 * moved. It had not: the funds stayed in the platform balance and somebody
 * paid the responder by hand. These columns are what makes the transfer real.
 *
 * Stripe will not accept a transfer to a person, only to a connected account,
 * so `stripe_account_id` is the whole precondition for paying anyone
 * automatically. It is null until the responder finishes onboarding, which is
 * their action and not something the platform can complete for them.
 *
 * `payouts_enabled` is deliberately separate from "an account exists".  Stripe
 * creates the account immediately but withholds payouts until identity and
 * bank details clear, and a transfer sent in that window fails. We mirror
 * Stripe's own flag rather than inferring readiness from the id being present.
 *
 * `payout_hold` is the manual brake: a responder under a safety review should
 * stop being paid without having to tear down their Stripe account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_stranded_responders')) {
            return;
        }

        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'stripe_account_id')) {
                $table->string('stripe_account_id', 64)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'payouts_enabled')) {
                $table->boolean('payouts_enabled')->default(false)->after('stripe_account_id');
            }

            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'onboarding_status')) {
                // not_started | pending | verified | restricted
                $table->string('onboarding_status', 20)->default('not_started')->after('payouts_enabled');
            }

            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'onboarding_synced_at')) {
                $table->timestamp('onboarding_synced_at')->nullable()->after('onboarding_status');
            }

            if (!Schema::hasColumn('urban_goodz_stranded_responders', 'payout_hold')) {
                $table->boolean('payout_hold')->default(false)->after('onboarding_synced_at');
            }
        });

        // Index name given explicitly and kept short: this project has hit
        // MySQL's 64-char identifier cap on auto-generated names before, and
        // that failure only shows up on a from-scratch migrate.
        $hasIndex = collect(DB::select(
            "SHOW INDEX FROM `urban_goodz_stranded_responders` WHERE Key_name = ?",
            ['ug_st_resp_stripe_acct_idx']
        ))->isNotEmpty();

        if (!$hasIndex) {
            Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
                $table->index('stripe_account_id', 'ug_st_resp_stripe_acct_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('urban_goodz_stranded_responders')) {
            return;
        }

        Schema::table('urban_goodz_stranded_responders', function (Blueprint $table) {
            foreach ([
                'stripe_account_id',
                'payouts_enabled',
                'onboarding_status',
                'onboarding_synced_at',
                'payout_hold',
            ] as $column) {
                if (Schema::hasColumn('urban_goodz_stranded_responders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
