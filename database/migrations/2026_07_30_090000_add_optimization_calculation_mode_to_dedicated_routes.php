<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_dedicated_routes')) {
            return;
        }

        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'optimization_calculation_mode')) {
                // ROAD_NETWORK | HAVERSINE_FALLBACK | MANUAL_ORDER
                $table->string('optimization_calculation_mode', 32)
                    ->nullable()
                    ->after('optimization_provider');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('urban_goodz_dedicated_routes')) {
            return;
        }

        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_dedicated_routes', 'optimization_calculation_mode')) {
                $table->dropColumn('optimization_calculation_mode');
            }
        });
    }
};
