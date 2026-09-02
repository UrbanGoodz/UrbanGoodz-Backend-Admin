<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_historical_reconstruction_configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_historical_reconstruction_configurations', 'owner_name')) {
                $table->string('owner_name', 255)->nullable()->after('configuration_name');
            }
            if (!Schema::hasColumn('urban_goodz_historical_reconstruction_configurations', 'owner_non_delivery_months')) {
                $table->json('owner_non_delivery_months')->nullable()->after('owner_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_historical_reconstruction_configurations', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'owner_non_delivery_months']);
        });
    }
};
