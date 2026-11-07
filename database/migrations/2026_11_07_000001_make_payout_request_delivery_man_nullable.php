<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor payouts share urban_goodz_driver_payout_requests but carry no driver, so the
 * original NOT NULL delivery_man_id made every vendor payout request fail with an
 * integrity-constraint error (found by the parallel payout race test).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urban_goodz_driver_payout_requests')
            || ! Schema::hasColumn('urban_goodz_driver_payout_requests', 'delivery_man_id')) {
            return;
        }

        Schema::table('urban_goodz_driver_payout_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_man_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left nullable: re-imposing NOT NULL would fail once vendor rows exist.
    }
};
