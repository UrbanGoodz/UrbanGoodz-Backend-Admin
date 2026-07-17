<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_driver_pricing_policies')) {
            Schema::create('urban_goodz_driver_pricing_policies', function (Blueprint $table) {
                $table->id();
                $table->string('policy_type'); // e.g. marketplace_delivery, courier_parcel, business_routes, dedicated_routes, logistics_loads, medical_courier, order_anywhere, returns_exceptions
                $table->string('name');
                $table->string('payout_model'); // fixed_payout, base_mileage, base_mileage_time, per_stop, per_package, percentage_of_revenue, dynamic_ai, manual_quote
                
                // Base pricing attributes
                $table->decimal('fixed_amount', 8, 2)->default(0.00);
                $table->decimal('base_fare', 8, 2)->default(0.00);
                $table->decimal('rate_per_mile', 8, 2)->default(0.00);
                $table->decimal('rate_per_minute', 8, 2)->default(0.00);
                $table->decimal('rate_per_stop', 8, 2)->default(0.00);
                $table->decimal('rate_per_package', 8, 2)->default(0.00);
                $table->decimal('revenue_percentage', 5, 2)->default(0.00);

                // Control toggles
                $table->boolean('dynamic_pricing_enabled')->default(false);
                $table->boolean('recommendation_only')->default(false);
                $table->boolean('auto_apply_within_limits')->default(false);
                $table->boolean('dispatcher_approval_required')->default(false);
                $table->boolean('admin_approval_required')->default(false);
                $table->boolean('live_pricing_enabled')->default(false);
                $table->boolean('sandbox_pricing_enabled')->default(true);

                // Additional adjustments
                $table->unsignedBigInteger('zone_id')->nullable(); // For zone overrides
                $table->json('vehicle_multipliers')->nullable();
                $table->decimal('urgency_premium', 8, 2)->default(0.00);
                $table->decimal('deadhead_pay_rate', 8, 2)->default(0.00);
                $table->decimal('waiting_pay_rate', 8, 2)->default(0.00);
                $table->decimal('return_pay_rate', 8, 2)->default(0.00);
                $table->decimal('exception_pay_rate', 8, 2)->default(0.00);
                $table->decimal('minimum_payout', 8, 2)->nullable();
                $table->decimal('maximum_payout', 8, 2)->nullable();
                $table->decimal('minimum_margin', 5, 2)->nullable(); // Minimum Urban Goodz Margin %

                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('policy_type');
                $table->index('zone_id');
                $table->index('is_active');
                $table->index(['policy_type', 'zone_id', 'is_active'], 'ug_driver_policy_lookup_idx');
                $table->index(['effective_from', 'effective_to'], 'ug_driver_policy_effective_idx');
                $table->foreign('zone_id', 'ug_driver_policy_zone_fk')
                    ->references('id')
                    ->on('zones')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('urban_goodz_driver_earnings')) {
            Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_driver_earnings', 'order_id')) {
                    $table->unsignedBigInteger('order_id')->nullable()->after('dedicated_route_id');
                    $table->index('order_id', 'ug_earn_order_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_driver_pricing_policies');

        if (Schema::hasTable('urban_goodz_driver_earnings')) {
            Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
                if (Schema::hasColumn('urban_goodz_driver_earnings', 'order_id')) {
                    $table->dropColumn('order_id');
                }
            });
        }
    }
};
