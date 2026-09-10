<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same gap as 2026_08_31_010100: Business Portal's AI route-performance
// endpoint scopes route batches by business_client_id, but the column was
// never added. Additive/nullable.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_route_batches') && !Schema::hasColumn('urban_goodz_route_batches', 'business_client_id')) {
            Schema::table('urban_goodz_route_batches', function (Blueprint $table) {
                $table->unsignedBigInteger('business_client_id')->nullable();
                $table->index('business_client_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_route_batches') && Schema::hasColumn('urban_goodz_route_batches', 'business_client_id')) {
            Schema::table('urban_goodz_route_batches', function (Blueprint $table) {
                try { $table->dropIndex(['business_client_id']); } catch (\Throwable $e) {}
                $table->dropColumn('business_client_id');
            });
        }
    }
};
