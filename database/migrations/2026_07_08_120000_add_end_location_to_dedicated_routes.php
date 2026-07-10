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
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'end_location')) {
                    $table->text('end_location')->nullable()->after('pickup_location');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'end_lat')) {
                    $table->decimal('end_lat', 10, 7)->nullable()->after('end_location');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'end_lng')) {
                    $table->decimal('end_lng', 10, 7)->nullable()->after('end_lat');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            $table->dropColumn(['end_location', 'end_lat', 'end_lng']);
        });
    }
};
