<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            $table->boolean('return_to_origin')->default(false)->after('end_lng');
            $table->string('optimization_status', 40)->default('not_optimized')->after('estimated_duration');
            $table->timestamp('optimized_at')->nullable()->after('optimization_status');
            $table->decimal('original_distance_miles', 10, 2)->nullable()->after('optimized_at');
            $table->decimal('optimized_distance_miles', 10, 2)->nullable()->after('original_distance_miles');
            $table->unsignedInteger('original_duration_minutes')->nullable()->after('optimized_distance_miles');
            $table->unsignedInteger('optimized_duration_minutes')->nullable()->after('original_duration_minutes');
            $table->string('optimization_method', 100)->nullable()->after('optimized_duration_minutes');
            $table->string('optimization_provider', 100)->nullable()->after('optimization_method');
            $table->text('optimization_error')->nullable()->after('optimization_provider');
            $table->json('optimization_original_sequence')->nullable()->after('optimization_error');
            $table->boolean('optimization_manual_override')->default(false)->after('optimization_original_sequence');
            $table->string('optimized_by_type', 30)->nullable()->after('optimization_manual_override');
            $table->unsignedBigInteger('optimized_by_id')->nullable()->after('optimized_by_type');
            $table->unsignedInteger('optimization_version')->default(0)->after('optimized_by_id');
        });

        Schema::table('urban_goodz_route_optimization_stops', function (Blueprint $table) {
            $table->unsignedInteger('original_stop_order')->nullable()->after('stop_order');
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_route_optimization_stops', function (Blueprint $table) {
            $table->dropColumn('original_stop_order');
        });

        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            $table->dropColumn([
                'return_to_origin',
                'optimization_status',
                'optimized_at',
                'original_distance_miles',
                'optimized_distance_miles',
                'original_duration_minutes',
                'optimized_duration_minutes',
                'optimization_method',
                'optimization_provider',
                'optimization_error',
                'optimization_original_sequence',
                'optimization_manual_override',
                'optimized_by_type',
                'optimized_by_id',
                'optimization_version',
            ]);
        });
    }
};
