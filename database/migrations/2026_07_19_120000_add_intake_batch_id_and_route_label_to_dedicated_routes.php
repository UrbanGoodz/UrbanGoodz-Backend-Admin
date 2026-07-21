<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'intake_batch_id')) {
                $table->foreignId('intake_batch_id')->nullable()->constrained('urban_goodz_intake_batches')->nullOnDelete();
            }
            if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'route_label')) {
                $table->string('route_label')->nullable()->after('route_name');
            }
            if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'business_client_id')) {
                $table->unsignedBigInteger('business_client_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_dedicated_routes', 'intake_batch_id')) {
                $table->dropForeign(['intake_batch_id']);
                $table->dropColumn('intake_batch_id');
            }
            if (Schema::hasColumn('urban_goodz_dedicated_routes', 'route_label')) {
                $table->dropColumn('route_label');
            }
        });
    }
};
