<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_men')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                if (!Schema::hasColumn('delivery_men', 'vehicle_type')) {
                    $table->string('vehicle_type')->nullable();
                }
                if (!Schema::hasColumn('delivery_men', 'cargo_capacity_notes')) {
                    $table->text('cargo_capacity_notes')->nullable();
                }
                if (!Schema::hasColumn('delivery_men', 'max_package_count')) {
                    $table->unsignedInteger('max_package_count')->nullable();
                }
                if (!Schema::hasColumn('delivery_men', 'max_weight_lbs')) {
                    $table->decimal('max_weight_lbs', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('delivery_men', 'has_cargo_space')) {
                    $table->boolean('has_cargo_space')->default(false);
                }
                if (!Schema::hasColumn('delivery_men', 'has_cooler_bag')) {
                    $table->boolean('has_cooler_bag')->default(false);
                }
                if (!Schema::hasColumn('delivery_men', 'has_medical_courier_training')) {
                    $table->boolean('has_medical_courier_training')->default(false);
                }
                if (!Schema::hasColumn('delivery_men', 'has_liftgate')) {
                    $table->boolean('has_liftgate')->default(false);
                }
                if (!Schema::hasColumn('delivery_men', 'preferred_zones')) {
                    $table->json('preferred_zones')->nullable();
                }
                if (!Schema::hasColumn('delivery_men', 'preferred_work_types')) {
                    $table->json('preferred_work_types')->nullable();
                }
                if (!Schema::hasColumn('delivery_men', 'capability_tags')) {
                    $table->json('capability_tags')->nullable();
                }
                if (!Schema::hasColumn('delivery_men', 'availability_preference')) {
                    $table->string('availability_preference')->default('standard');
                }
                if (!Schema::hasColumn('delivery_men', 'available_for_business_courier')) {
                    $table->boolean('available_for_business_courier')->default(false);
                }
                if (!Schema::hasColumn('delivery_men', 'available_for_package_routes')) {
                    $table->boolean('available_for_package_routes')->default(false);
                }
                if (!Schema::hasColumn('delivery_men', 'available_for_order_anywhere')) {
                    $table->boolean('available_for_order_anywhere')->default(false);
                }
                if (!Schema::hasColumn('delivery_men', 'available_for_medical_courier')) {
                    $table->boolean('available_for_medical_courier')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_men')) {
            $columns = [
                'vehicle_type',
                'cargo_capacity_notes',
                'max_package_count',
                'max_weight_lbs',
                'has_cargo_space',
                'has_cooler_bag',
                'has_medical_courier_training',
                'has_liftgate',
                'preferred_zones',
                'preferred_work_types',
                'capability_tags',
                'availability_preference',
                'available_for_business_courier',
                'available_for_package_routes',
                'available_for_order_anywhere',
                'available_for_medical_courier',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('delivery_men', $column)) {
                    Schema::table('delivery_men', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};