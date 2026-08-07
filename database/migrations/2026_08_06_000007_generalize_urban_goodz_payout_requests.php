<?php

use App\Services\UrbanGoodzPayoutSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Opens instant payouts to vendors as well as drivers.
 *
 * `urban_goodz_driver_payout_requests` already had the right shape --
 * payout_type, instant_fee, net_amount -- but was keyed to delivery_man_id
 * alone. Vendors cash out through the shared 6amMart `withdraw_requests`
 * table, which has an amount and no fee column at all, so there was nowhere
 * to record what an instant payout cost them.
 *
 * Rather than bolt a fee onto the shared table -- which existing store and
 * deliveryman disbursement flows also write to -- this table becomes the
 * single place a *fee-bearing* payout is recorded, for either kind of payee.
 * The table holds zero rows, so generalising it now costs nothing.
 *
 * The fee basis is stored alongside the amount on purpose. A payout is a
 * financial record: it has to stay explainable years later, after the
 * configured rate has changed. Storing only the resulting figure would leave
 * nobody able to say how it was reached.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_driver_payout_requests')) {
            return;
        }

        Schema::table('urban_goodz_driver_payout_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_driver_payout_requests', 'payee_type')) {
                // driver | vendor
                $table->string('payee_type', 20)->default('driver')->after('id');
            }
            if (!Schema::hasColumn('urban_goodz_driver_payout_requests', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('delivery_man_id');
            }

            // What the fee was worked out from, so the figure stays
            // explainable after the configured rate changes.
            if (!Schema::hasColumn('urban_goodz_driver_payout_requests', 'fee_percent_bps')) {
                $table->unsignedInteger('fee_percent_bps')->default(0)->after('instant_fee');
            }
            if (!Schema::hasColumn('urban_goodz_driver_payout_requests', 'fee_minimum')) {
                $table->decimal('fee_minimum', 10, 2)->default(0)->after('fee_percent_bps');
            }
            if (!Schema::hasColumn('urban_goodz_driver_payout_requests', 'fee_cap')) {
                $table->decimal('fee_cap', 10, 2)->nullable()->after('fee_minimum');
            }
        });

        // Guarded individually. An earlier run of this migration added the
        // columns and then threw before Laravel recorded it, so on that host
        // the columns exist while the migration does not -- and an unguarded
        // index would collide on the retry.
        $this->addIndexIfMissing('ug_payout_payee_status_idx', ['payee_type', 'status']);
        $this->addIndexIfMissing('ug_payout_vendor_status_idx', ['vendor_id', 'status']);

        // Materialise the rates so they are visible and editable immediately,
        // rather than existing only as code defaults until somebody saves.
        // Seeded through the settings class so the keys are declared once.
        foreach (UrbanGoodzPayoutSettings::all() as $key => $value) {
            UrbanGoodzPayoutSettings::put($key, $value);
        }
    }

    private function addIndexIfMissing(string $name, array $columns): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS found FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            ['urban_goodz_driver_payout_requests', $name]
        );

        if ($exists) {
            return;
        }

        Schema::table('urban_goodz_driver_payout_requests', function (Blueprint $table) use ($name, $columns) {
            $table->index($columns, $name);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('urban_goodz_driver_payout_requests')) {
            return;
        }

        Schema::table('urban_goodz_driver_payout_requests', function (Blueprint $table) {
            $table->dropIndex('ug_payout_payee_status_idx');
            $table->dropIndex('ug_payout_vendor_status_idx');
            $table->dropColumn(['payee_type', 'vendor_id', 'fee_percent_bps', 'fee_minimum', 'fee_cap']);
        });
    }
};
