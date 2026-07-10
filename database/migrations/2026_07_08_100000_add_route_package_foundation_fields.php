<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'returned_packages')) {
                    $table->integer('returned_packages')->default(0)->after('failed_packages');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'payout_model')) {
                    $table->string('payout_model', 50)->nullable()->after('weekly_payout_allowed');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'route_offer_amount')) {
                    $table->decimal('route_offer_amount', 12, 2)->nullable()->after('payout_model');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'estimated_miles')) {
                    $table->decimal('estimated_miles', 10, 2)->nullable()->after('route_offer_amount');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'estimated_duration')) {
                    $table->integer('estimated_duration')->nullable()->after('estimated_miles');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_route_packages', 'dropoff_city')) {
                    $table->string('dropoff_city', 255)->nullable()->after('dropoff_address');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'dropoff_state')) {
                    $table->string('dropoff_state', 255)->nullable()->after('dropoff_city');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'dropoff_zip')) {
                    $table->string('dropoff_zip', 20)->nullable()->after('dropoff_state');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'stop_order')) {
                    $table->integer('stop_order')->default(0)->after('dropoff_zip');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'delivery_result')) {
                    $table->string('delivery_result', 100)->nullable()->after('status');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'delivered_to_name')) {
                    $table->string('delivered_to_name', 255)->nullable()->after('delivery_result');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'delivered_location_type')) {
                    $table->string('delivered_location_type', 100)->nullable()->after('delivered_to_name');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'return_required')) {
                    $table->boolean('return_required')->default(false)->after('delivered_location_type');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'returned_at')) {
                    $table->timestamp('returned_at')->nullable()->after('return_required');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'return_location')) {
                    $table->text('return_location')->nullable()->after('returned_at');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'payout_status')) {
                    $table->string('payout_status', 50)->default('pending')->after('return_location');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'payout_eligible')) {
                    $table->boolean('payout_eligible')->default(false)->after('payout_status');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'geocode_status')) {
                    $table->string('geocode_status', 50)->default('pending')->after('payout_eligible');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'geocode_confidence')) {
                    $table->decimal('geocode_confidence', 5, 2)->nullable()->after('geocode_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
                $table->dropColumn([
                    'returned_packages', 'payout_model', 'route_offer_amount',
                    'estimated_miles', 'estimated_duration',
                ]);
            });
        }

        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                $table->dropColumn([
                    'dropoff_city', 'dropoff_state', 'dropoff_zip', 'stop_order',
                    'delivery_result', 'delivered_to_name', 'delivered_location_type',
                    'return_required', 'returned_at', 'return_location',
                    'payout_status', 'payout_eligible',
                    'geocode_status', 'geocode_confidence',
                ]);
            });
        }
    }
};
