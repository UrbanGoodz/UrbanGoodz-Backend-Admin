<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            $existing = Schema::getColumnListing('urban_goodz_dedicated_routes');
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) use ($existing) {
                if (!in_array('return_to_origin', $existing, true)) {
                    $table->boolean('return_to_origin')->default(false)->after('end_lng');
                }
                if (!in_array('optimization_status', $existing, true)) {
                    $table->string('optimization_status', 40)->default('not_optimized')->after('estimated_duration');
                }
                if (!in_array('optimized_at', $existing, true)) {
                    $table->timestamp('optimized_at')->nullable()->after('optimization_status');
                }
                if (!in_array('original_distance_miles', $existing, true)) {
                    $table->decimal('original_distance_miles', 10, 2)->nullable()->after('optimized_at');
                }
                if (!in_array('optimized_distance_miles', $existing, true)) {
                    $table->decimal('optimized_distance_miles', 10, 2)->nullable()->after('original_distance_miles');
                }
                if (!in_array('original_duration_minutes', $existing, true)) {
                    $table->unsignedInteger('original_duration_minutes')->nullable()->after('optimized_distance_miles');
                }
                if (!in_array('optimized_duration_minutes', $existing, true)) {
                    $table->unsignedInteger('optimized_duration_minutes')->nullable()->after('original_duration_minutes');
                }
                if (!in_array('optimization_method', $existing, true)) {
                    $table->string('optimization_method', 100)->nullable()->after('optimized_duration_minutes');
                }
                if (!in_array('optimization_provider', $existing, true)) {
                    $table->string('optimization_provider', 100)->nullable()->after('optimization_method');
                }
                if (!in_array('optimization_error', $existing, true)) {
                    $table->text('optimization_error')->nullable()->after('optimization_provider');
                }
                if (!in_array('optimization_original_sequence', $existing, true)) {
                    $table->json('optimization_original_sequence')->nullable()->after('optimization_error');
                }
                if (!in_array('optimization_manual_override', $existing, true)) {
                    $table->boolean('optimization_manual_override')->default(false)->after('optimization_original_sequence');
                }
                if (!in_array('optimized_by_type', $existing, true)) {
                    $table->string('optimized_by_type', 30)->nullable()->after('optimization_manual_override');
                }
                if (!in_array('optimized_by_id', $existing, true)) {
                    $table->unsignedBigInteger('optimized_by_id')->nullable()->after('optimized_by_type');
                }
                if (!in_array('optimization_version', $existing, true)) {
                    $table->unsignedInteger('optimization_version')->default(0)->after('optimized_by_id');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_route_optimization_stops')
            && !Schema::hasColumn('urban_goodz_route_optimization_stops', 'original_stop_order')) {
            Schema::table('urban_goodz_route_optimization_stops', function (Blueprint $table) {
                $table->unsignedInteger('original_stop_order')->nullable()->after('stop_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_route_optimization_stops')
            && Schema::hasColumn('urban_goodz_route_optimization_stops', 'original_stop_order')) {
            Schema::table('urban_goodz_route_optimization_stops', function (Blueprint $table) {
                $table->dropColumn('original_stop_order');
            });
        }

        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            $columns = array_values(array_filter([
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
            ], fn (string $column) => Schema::hasColumn('urban_goodz_dedicated_routes', $column)));

            if ($columns !== []) {
                Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
