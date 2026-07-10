<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_men', 'has_trailer')) {
                $table->boolean('has_trailer')->default(false);
            }
            if (!Schema::hasColumn('delivery_men', 'trailer_type')) {
                $table->string('trailer_type')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'trailer_length_feet')) {
                $table->decimal('trailer_length_feet', 6, 2)->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'trailer_width_feet')) {
                $table->decimal('trailer_width_feet', 6, 2)->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'trailer_capacity_lbs')) {
                $table->decimal('trailer_capacity_lbs', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'hitch_type')) {
                $table->string('hitch_type')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'trailer_plate_number')) {
                $table->string('trailer_plate_number')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'trailer_registration_expiration')) {
                $table->date('trailer_registration_expiration')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'trailer_insurance_expiration')) {
                $table->date('trailer_insurance_expiration')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'cdl_status')) {
                $table->string('cdl_status')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'cdl_class')) {
                $table->string('cdl_class')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'cdl_number')) {
                $table->string('cdl_number')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'dot_number')) {
                $table->string('dot_number')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'mc_number')) {
                $table->string('mc_number')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'has_pallet_jack')) {
                $table->boolean('has_pallet_jack')->default(false);
            }
            if (!Schema::hasColumn('delivery_men', 'has_hazmat')) {
                $table->boolean('has_hazmat')->default(false);
            }
            if (!Schema::hasColumn('delivery_men', 'has_cargo_insurance')) {
                $table->boolean('has_cargo_insurance')->default(false);
            }
            if (!Schema::hasColumn('delivery_men', 'cargo_insurance_expiration')) {
                $table->date('cargo_insurance_expiration')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'max_payload_lbs')) {
                $table->decimal('max_payload_lbs', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'cargo_length_inches')) {
                $table->decimal('cargo_length_inches', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'cargo_width_inches')) {
                $table->decimal('cargo_width_inches', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'cargo_height_inches')) {
                $table->decimal('cargo_height_inches', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'vehicle_photos')) {
                $table->json('vehicle_photos')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'registration_expiration')) {
                $table->date('registration_expiration')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'insurance_expiration')) {
                $table->date('insurance_expiration')->nullable();
            }
            if (!Schema::hasColumn('delivery_men', 'inspection_expiration')) {
                $table->date('inspection_expiration')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $columns = [
                'has_trailer', 'trailer_type', 'trailer_length_feet', 'trailer_width_feet',
                'trailer_capacity_lbs', 'hitch_type', 'trailer_plate_number',
                'trailer_registration_expiration', 'trailer_insurance_expiration',
                'cdl_status', 'cdl_class', 'cdl_number', 'dot_number', 'mc_number',
                'has_pallet_jack', 'has_hazmat', 'has_cargo_insurance', 'cargo_insurance_expiration',
                'max_payload_lbs', 'cargo_length_inches', 'cargo_width_inches', 'cargo_height_inches',
                'vehicle_photos', 'registration_expiration', 'insurance_expiration', 'inspection_expiration',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('delivery_men', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
