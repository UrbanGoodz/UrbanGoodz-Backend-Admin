<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_men')) {
            Schema::create('delivery_men', function (Blueprint $table) {
                $table->id();
                $table->string('f_name', 100)->nullable();
                $table->string('l_name', 100)->nullable();
                $table->string('phone', 20);
                $table->string('email', 100)->nullable();
                $table->string('password', 100);
                $table->rememberToken();
                $table->timestamps();
                $table->string('image', 255)->nullable();
                $table->string('identity_image', 1000)->nullable();
                $table->boolean('status')->default(true);
                $table->boolean('active')->default(0);
                $table->boolean('available')->default(0);
                $table->decimal('earning', 24, 3)->default(0);
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->integer('current_orders')->default(0);
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->integer('max_package_count')->default(0);
                $table->decimal('max_weight_lbs', 24, 2)->default(0);
                $table->boolean('has_cargo_space')->default(false);
                $table->boolean('has_cooler_bag')->default(false);
                $table->boolean('has_medical_courier_training')->default(false);
                $table->boolean('has_liftgate')->default(false);
                $table->json('preferred_zones')->nullable();
                $table->json('preferred_work_types')->nullable();
                $table->json('capability_tags')->nullable();
                $table->boolean('available_for_business_courier')->default(false);
                $table->boolean('available_for_package_routes')->default(false);
                $table->boolean('available_for_order_anywhere')->default(false);
                $table->boolean('available_for_medical_courier')->default(false);
                $table->string('auth_token', 255)->nullable();
                $table->string('firebase_token', 255)->nullable();
                $table->boolean('is_ride')->default(false);
                $table->string('type', 50)->default('zone_wise');
                $table->string('application_status', 50)->default('pending');
                $table->string('rejection_note', 500)->nullable();
                $table->string('ref_by', 50)->nullable();
                $table->decimal('loyalty_point', 24, 3)->default(0);
                $table->decimal('private_endpoint_lat', 10, 7)->nullable();
                $table->decimal('private_endpoint_lng', 10, 7)->nullable();
                $table->string('private_endpoint_status', 50)->nullable();
                $table->string('join_as', 50)->nullable();
                $table->boolean('has_trailer')->default(false);
                $table->decimal('trailer_length_feet', 24, 2)->default(0);
                $table->decimal('trailer_width_feet', 24, 2)->default(0);
                $table->decimal('trailer_capacity_lbs', 24, 2)->default(0);
                $table->date('trailer_registration_expiration')->nullable();
                $table->date('trailer_insurance_expiration')->nullable();
                $table->boolean('has_pallet_jack')->default(false);
                $table->boolean('has_hazmat')->default(false);
                $table->boolean('has_cargo_insurance')->default(false);
                $table->date('cargo_insurance_expiration')->nullable();
                $table->decimal('max_payload_lbs', 24, 2)->default(0);
                $table->decimal('cargo_length_inches', 24, 2)->default(0);
                $table->decimal('cargo_width_inches', 24, 2)->default(0);
                $table->decimal('cargo_height_inches', 24, 2)->default(0);
                $table->json('vehicle_photos')->nullable();
                $table->date('registration_expiration')->nullable();
                $table->date('insurance_expiration')->nullable();
                $table->date('inspection_expiration')->nullable();
                $table->integer('vehicle_year')->nullable();
                $table->date('cdl_expiration')->nullable();
                $table->boolean('load_board_eligible')->default(false);
                $table->string('ref_code', 20)->nullable()->unique();
                $table->string('login_remember_token', 255)->nullable();
                $table->string('vehicle_identity_number', 100)->nullable();
                $table->string('vehicle_identity_image', 255)->nullable();
                $table->unique(['phone'], 'delivery_men_phone_unique');
                $table->unique(['email'], 'delivery_men_email_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_men');
    }
};