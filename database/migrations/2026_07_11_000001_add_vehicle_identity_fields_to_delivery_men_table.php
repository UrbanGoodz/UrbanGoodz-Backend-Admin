<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $columns = [
                'vehicle_make' => fn(Blueprint $t) => $t->string('vehicle_make')->nullable(),
                'vehicle_model' => fn(Blueprint $t) => $t->string('vehicle_model')->nullable(),
                'vehicle_year' => fn(Blueprint $t) => $t->integer('vehicle_year')->nullable(),
                'vehicle_color' => fn(Blueprint $t) => $t->string('vehicle_color')->nullable(),
                'vehicle_vin' => fn(Blueprint $t) => $t->string('vehicle_vin', 20)->nullable(),
                'license_plate' => fn(Blueprint $t) => $t->string('license_plate', 20)->nullable(),
                'trailer_vin' => fn(Blueprint $t) => $t->string('trailer_vin', 20)->nullable(),
                'trailer_make' => fn(Blueprint $t) => $t->string('trailer_make')->nullable(),
                'trailer_model' => fn(Blueprint $t) => $t->string('trailer_model')->nullable(),
                'cdl_state' => fn(Blueprint $t) => $t->string('cdl_state', 2)->nullable(),
                'cdl_expiration' => fn(Blueprint $t) => $t->date('cdl_expiration')->nullable(),
                'usdot_number' => fn(Blueprint $t) => $t->string('usdot_number', 20)->nullable(),
                'insurance_policy' => fn(Blueprint $t) => $t->string('insurance_policy', 50)->nullable(),
                'insurance_carrier' => fn(Blueprint $t) => $t->string('insurance_carrier')->nullable(),
                'load_board_eligible' => fn(Blueprint $t) => $t->boolean('load_board_eligible')->default(false),
            ];

            foreach ($columns as $name => $adder) {
                if (!Schema::hasColumn('delivery_men', $name)) {
                    $adder($table);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $columns = [
                'vehicle_make', 'vehicle_model', 'vehicle_year', 'vehicle_color',
                'vehicle_vin', 'license_plate', 'trailer_vin', 'trailer_make',
                'trailer_model', 'cdl_state', 'cdl_expiration', 'usdot_number',
                'insurance_policy', 'insurance_carrier', 'load_board_eligible',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('delivery_men', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
