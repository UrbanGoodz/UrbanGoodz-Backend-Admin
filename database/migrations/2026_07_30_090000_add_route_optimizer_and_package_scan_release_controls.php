<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'source_module')) {
                    $table->string('source_module', 60)->default('package_routes')->after('route_type');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'source_record_type')) {
                    $table->string('source_record_type', 100)->nullable()->after('source_module');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'source_record_id')) {
                    $table->string('source_record_id', 100)->nullable()->after('source_record_type');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'capacity_packages')) {
                    $table->unsignedInteger('capacity_packages')->nullable()->after('max_packages_per_batch');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'capacity_weight_lbs')) {
                    $table->decimal('capacity_weight_lbs', 12, 3)->nullable()->after('capacity_packages');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'optimization_distance_mode')) {
                    $table->string('optimization_distance_mode', 30)->nullable()->after('optimization_provider');
                }
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'optimization_constraints')) {
                    $table->json('optimization_constraints')->nullable()->after('optimization_distance_mode');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_route_packages', 'group_stop_order')) {
                    $table->unsignedInteger('group_stop_order')->nullable()->after('stop_order');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'delivery_group_key')) {
                    $table->string('delivery_group_key', 64)->nullable()->after('group_stop_order');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'stop_locked')) {
                    $table->boolean('stop_locked')->default(false)->after('stop_order');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'locked_stop_order')) {
                    $table->unsignedInteger('locked_stop_order')->nullable()->after('stop_locked');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'source_module')) {
                    $table->string('source_module', 60)->default('package_routes')->after('external_reference');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'source_record_id')) {
                    $table->string('source_record_id', 100)->nullable()->after('source_module');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'redelivery_attempts')) {
                    $table->unsignedInteger('redelivery_attempts')->default(0)->after('return_location');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'last_exception_at')) {
                    $table->timestamp('last_exception_at')->nullable()->after('redelivery_attempts');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_route_optimization_stops')) {
            Schema::table('urban_goodz_route_optimization_stops', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_route_optimization_stops', 'group_stop_order')) {
                    $table->unsignedInteger('group_stop_order')->nullable()->after('stop_order');
                }
                if (!Schema::hasColumn('urban_goodz_route_optimization_stops', 'delivery_group_key')) {
                    $table->string('delivery_group_key', 64)->nullable()->after('group_stop_order');
                }
            });
        }

        if (Schema::hasColumn('urban_goodz_dedicated_routes', 'source_module')) {
            DB::table('urban_goodz_dedicated_routes')
                ->whereNotNull('route_type')
                ->update(['source_module' => DB::raw('route_type')]);
        }
        if (Schema::hasTable('urban_goodz_route_packages')
            && Schema::hasColumn('urban_goodz_route_packages', 'source_module')) {
            DB::table('urban_goodz_dedicated_routes')
                ->select(['id', 'source_module'])
                ->orderBy('id')
                ->chunkById(500, function ($routes): void {
                    foreach ($routes as $route) {
                        DB::table('urban_goodz_route_packages')
                            ->where('dedicated_route_id', $route->id)
                            ->update(['source_module' => $route->source_module]);
                    }
                });
        }

        if (Schema::hasColumn('urban_goodz_route_packages', 'delivery_group_key')) {
            DB::table('urban_goodz_dedicated_routes')
                ->select('id')
                ->orderBy('id')
                ->chunkById(250, function ($routes): void {
                    foreach ($routes as $route) {
                        $groups = [];
                        $nextGroupOrder = 1;
                        $packages = DB::table('urban_goodz_route_packages')
                            ->select(['id', 'dropoff_address', 'dropoff_lat', 'dropoff_lng'])
                            ->where('dedicated_route_id', $route->id)
                            ->orderBy('stop_order')
                            ->orderBy('id')
                            ->get();

                        foreach ($packages as $package) {
                            $address = strtolower(trim(preg_replace('/\s+/', ' ', (string) $package->dropoff_address)));
                            $latitude = is_numeric($package->dropoff_lat)
                                ? number_format((float) $package->dropoff_lat, 5, '.', '')
                                : '';
                            $longitude = is_numeric($package->dropoff_lng)
                                ? number_format((float) $package->dropoff_lng, 5, '.', '')
                                : '';
                            $key = $address === '' && $latitude === '' && $longitude === ''
                                ? "package:{$package->id}"
                                : hash('sha256', "{$address}|{$latitude}|{$longitude}");
                            $groups[$key] ??= $nextGroupOrder++;

                            DB::table('urban_goodz_route_packages')->where('id', $package->id)->update([
                                'delivery_group_key' => $key,
                                'group_stop_order' => $groups[$key],
                            ]);
                            if (Schema::hasTable('urban_goodz_route_optimization_stops')) {
                                DB::table('urban_goodz_route_optimization_stops')
                                    ->where('package_id', $package->id)
                                    ->update([
                                        'delivery_group_key' => $key,
                                        'group_stop_order' => $groups[$key],
                                    ]);
                            }
                        }
                    }
                });
        }

        if (Schema::hasTable('urban_goodz_package_scans')) {
            Schema::table('urban_goodz_package_scans', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_package_scans', 'idempotency_key')) {
                    $table->string('idempotency_key', 100)->nullable()->unique('ug_scan_idempotency_uniq');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'business_client_id')) {
                    $table->unsignedBigInteger('business_client_id')->nullable()->index('ug_scan_business_idx');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'dedicated_route_id')) {
                    $table->unsignedBigInteger('dedicated_route_id')->nullable()->index('ug_scan_route_idx');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'input_method')) {
                    $table->string('input_method', 30)->default('manual')->after('scanner_type');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'device_id')) {
                    $table->string('device_id', 100)->nullable()->after('input_method');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'occurred_at')) {
                    $table->timestamp('occurred_at')->nullable()->after('device_id');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'received_at')) {
                    $table->timestamp('received_at')->nullable()->after('occurred_at');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'was_offline')) {
                    $table->boolean('was_offline')->default(false)->after('received_at');
                }
                if (!Schema::hasColumn('urban_goodz_package_scans', 'metadata')) {
                    $table->json('metadata')->nullable()->after('notes');
                }
            });
        }

        if (!Schema::hasTable('urban_goodz_route_optimization_histories')) {
            Schema::create('urban_goodz_route_optimization_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dedicated_route_id');
                $table->unsignedInteger('version');
                $table->string('action', 40);
                $table->string('status', 40);
                $table->string('method', 100)->nullable();
                $table->string('provider', 150)->nullable();
                $table->string('distance_mode', 30);
                $table->json('original_sequence');
                $table->json('result_sequence');
                $table->json('result_stop_groups')->nullable();
                $table->json('constraints')->nullable();
                $table->unsignedInteger('package_count')->default(0);
                $table->unsignedInteger('stop_count')->default(0);
                $table->decimal('original_distance_miles', 12, 3)->nullable();
                $table->decimal('result_distance_miles', 12, 3)->nullable();
                $table->unsignedInteger('original_duration_minutes')->nullable();
                $table->unsignedInteger('result_duration_minutes')->nullable();
                $table->string('actor_type', 30)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamps();

                $table->unique(['dedicated_route_id', 'version'], 'ug_route_opt_history_version_uniq');
                $table->index(['dedicated_route_id', 'created_at'], 'ug_route_opt_history_route_idx');
            });
        }
        if (Schema::hasTable('urban_goodz_route_optimization_histories')
            && !Schema::hasColumn('urban_goodz_route_optimization_histories', 'result_stop_groups')) {
            Schema::table('urban_goodz_route_optimization_histories', function (Blueprint $table) {
                $table->json('result_stop_groups')->nullable()->after('result_sequence');
            });
        }

        if (!Schema::hasTable('urban_goodz_route_operational_metrics')) {
            Schema::create('urban_goodz_route_operational_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dedicated_route_id');
                $table->unsignedInteger('completion_version')->default(1);
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->unsignedBigInteger('business_client_id')->nullable();
                $table->unsignedBigInteger('miles_milli')->default(0);
                $table->unsignedBigInteger('eligible_miles_milli')->default(0);
                $table->string('mileage_eligibility', 60);
                $table->unsignedInteger('accepted_optimization_version')->default(0);
                $table->unsignedInteger('package_count')->default(0);
                $table->unsignedInteger('stop_count')->default(0);
                $table->unsignedInteger('return_count')->default(0);
                $table->unsignedInteger('exception_count')->default(0);
                $table->unsignedInteger('duration_minutes')->default(0);
                $table->string('distance_mode', 30);
                $table->string('provider', 150)->nullable();
                $table->timestamp('verified_at');
                $table->timestamps();

                $table->unique(
                    ['dedicated_route_id', 'completion_version'],
                    'ug_route_metrics_completion_uniq'
                );
            });
        }
        if (Schema::hasTable('urban_goodz_route_operational_metrics')) {
            Schema::table('urban_goodz_route_operational_metrics', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_route_operational_metrics', 'eligible_miles_milli')) {
                    $table->unsignedBigInteger('eligible_miles_milli')->default(0)->after('miles_milli');
                }
                if (!Schema::hasColumn('urban_goodz_route_operational_metrics', 'mileage_eligibility')) {
                    $table->string('mileage_eligibility', 60)
                        ->default('ineligible_non_road_or_unaccepted')
                        ->after('eligible_miles_milli');
                }
                if (!Schema::hasColumn('urban_goodz_route_operational_metrics', 'accepted_optimization_version')) {
                    $table->unsignedInteger('accepted_optimization_version')
                        ->default(0)
                        ->after('mileage_eligibility');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_route_operational_metrics');
        Schema::dropIfExists('urban_goodz_route_optimization_histories');

        if (Schema::hasTable('urban_goodz_route_optimization_stops')) {
            Schema::table('urban_goodz_route_optimization_stops', function (Blueprint $table) {
                foreach (['group_stop_order', 'delivery_group_key'] as $column) {
                    if (Schema::hasColumn('urban_goodz_route_optimization_stops', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('urban_goodz_package_scans')) {
            Schema::table('urban_goodz_package_scans', function (Blueprint $table) {
                $columns = [
                    'dedicated_route_id', 'input_method', 'device_id',
                    'received_at', 'was_offline',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('urban_goodz_package_scans', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                foreach ([
                    'group_stop_order', 'delivery_group_key',
                    'stop_locked', 'locked_stop_order', 'source_module',
                    'source_record_id', 'redelivery_attempts', 'last_exception_at',
                ] as $column) {
                    if (Schema::hasColumn('urban_goodz_route_packages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
                foreach ([
                    'source_module', 'source_record_type', 'source_record_id',
                    'capacity_packages', 'capacity_weight_lbs',
                    'optimization_distance_mode', 'optimization_constraints',
                ] as $column) {
                    if (Schema::hasColumn('urban_goodz_dedicated_routes', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
