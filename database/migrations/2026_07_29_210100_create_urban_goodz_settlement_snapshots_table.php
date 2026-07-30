<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable settlement snapshot.
 *
 * Every completed revenue transaction must be reconstructable exactly as it was
 * settled, using the rules in force at that moment. Before this table only
 * `order_transactions.commission_percentage` recorded anything of the sort, and
 * only for marketplace orders — driver earnings, loads, routes, bookings and
 * rentals kept an amount with no record of what produced it, so a later rate
 * change silently made history unexplainable and refunds could not honour the
 * original terms.
 *
 * Rows are written once. `UrbanGoodzSettlementSnapshot` blocks update and
 * delete; corrections are made by writing a new reversing snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_settlement_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();

            // The thing being settled: order, load, route, job, booking,
            // rental, campaign, ...
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->string('transaction_type')->index();
            $table->unsignedBigInteger('module_id')->nullable();
            $table->string('partner_type')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();

            // Side A — business/provider commission.
            $table->unsignedBigInteger('commission_rule_id')->nullable();
            $table->unsignedInteger('commission_rule_version')->nullable();
            $table->string('commission_calculation_type')->nullable();
            $table->decimal('commission_rate_percent', 8, 4)->nullable();
            $table->bigInteger('commission_fixed_amount_cents')->nullable();
            $table->string('commission_basis')->nullable();
            $table->bigInteger('qualifying_amount_cents')->default(0);
            $table->bigInteger('commission_amount_cents')->default(0);
            $table->bigInteger('partner_gross_cents')->default(0);
            $table->bigInteger('partner_net_cents')->default(0);

            // Side B — driver/fulfillment, calculated separately and never
            // netted against side A.
            $table->unsignedBigInteger('driver_comp_rule_id')->nullable();
            $table->unsignedInteger('driver_comp_rule_version')->nullable();
            $table->string('driver_comp_method')->nullable();
            $table->bigInteger('driver_gross_cents')->nullable();
            $table->bigInteger('driver_admin_fee_cents')->nullable();
            $table->bigInteger('driver_net_cents')->nullable();

            $table->string('currency', 8)->default('USD');

            // Verified operational inputs (miles, packages, stops, ...) and the
            // full rule row as it stood, so the maths can be replayed.
            $table->json('inputs')->nullable();
            $table->json('rule_snapshot')->nullable();

            $table->string('idempotency_key')->unique();
            $table->timestamp('effective_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id'], 'ug_ss_subject_idx');
            $table->index(['partner_type', 'partner_id'], 'ug_ss_partner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_settlement_snapshots');
    }
};
