<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Order Anywhere sourcing workflow only stores free-text store info.
     * When the request converts into a real Order we need concrete pickup
     * (store) and dropoff (customer) coordinates so the driver offer UI and
     * the nearest-driver matcher have real locations to work with.
     */
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_anywhere_requests', 'pickup_address')) {
                $table->string('pickup_address')->nullable()->after('store_vendor_address_or_website');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'pickup_latitude')) {
                $table->decimal('pickup_latitude', 10, 7)->nullable()->after('pickup_address');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'pickup_longitude')) {
                $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'dropoff_address')) {
                $table->string('dropoff_address')->nullable()->after('pickup_longitude');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'dropoff_latitude')) {
                $table->decimal('dropoff_latitude', 10, 7)->nullable()->after('dropoff_address');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'dropoff_longitude')) {
                $table->decimal('dropoff_longitude', 10, 7)->nullable()->after('dropoff_latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            foreach (['pickup_address', 'pickup_latitude', 'pickup_longitude', 'dropoff_address', 'dropoff_latitude', 'dropoff_longitude'] as $column) {
                if (Schema::hasColumn('order_anywhere_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
