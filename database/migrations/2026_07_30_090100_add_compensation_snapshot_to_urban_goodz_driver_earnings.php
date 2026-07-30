<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a driver earning reconstructable.
 *
 * The table recorded `amount` and an `earning_type` with no record of which
 * policy produced the figure, which method it used, or the verified miles,
 * packages and stops behind it. A rate change therefore made past earnings
 * unexplainable, and a driver disputing their pay could not be shown the
 * arithmetic.
 *
 * `amount` stays as-is for backwards compatibility; the cents columns are the
 * authoritative integer figures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            $table->unsignedBigInteger('pricing_policy_id')->nullable()->after('earning_type');
            $table->unsignedInteger('pricing_policy_version')->nullable()->after('pricing_policy_id');
            $table->string('payout_model')->nullable()->after('pricing_policy_version');

            $table->bigInteger('gross_cents')->nullable()->after('payout_model');
            $table->bigInteger('admin_fee_cents')->nullable()->after('gross_cents');
            $table->bigInteger('net_cents')->nullable()->after('admin_fee_cents');

            // Verified operational data the figure was derived from: eligible
            // miles, completed packages, completed stops, waiting minutes, ...
            $table->json('calculation_inputs')->nullable()->after('net_cents');
            // The policy row as it stood at calculation time.
            $table->json('policy_snapshot')->nullable()->after('calculation_inputs');

            $table->unsignedBigInteger('settlement_snapshot_id')->nullable()->after('policy_snapshot');
            $table->string('idempotency_key')->nullable()->after('settlement_snapshot_id');

            $table->unique('idempotency_key', 'ug_de_idempotency_unique');
            $table->index('pricing_policy_id', 'ug_de_policy_idx');
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            $table->dropUnique('ug_de_idempotency_unique');
            $table->dropIndex('ug_de_policy_idx');
            $table->dropColumn([
                'pricing_policy_id', 'pricing_policy_version', 'payout_model',
                'gross_cents', 'admin_fee_cents', 'net_cents',
                'calculation_inputs', 'policy_snapshot',
                'settlement_snapshot_id', 'idempotency_key',
            ]);
        });
    }
};
