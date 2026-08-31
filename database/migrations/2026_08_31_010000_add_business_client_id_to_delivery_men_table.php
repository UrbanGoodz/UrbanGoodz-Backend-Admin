<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Business Portal's AI Logistics module (load-board, driver-matching,
// dispatch) needs the concept of a business client's own dedicated driver
// fleet, distinct from `available_for_business_courier` (a general opt-in
// flag). Nullable and additive: a driver with no business_client_id is not
// dedicated to any business client, unaffected by this change.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_men', 'business_client_id')) {
                $table->unsignedBigInteger('business_client_id')->nullable()->after('zone_id');
                $table->index('business_client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_men', 'business_client_id')) {
                $table->dropIndex(['business_client_id']);
                $table->dropColumn('business_client_id');
            }
        });
    }
};
