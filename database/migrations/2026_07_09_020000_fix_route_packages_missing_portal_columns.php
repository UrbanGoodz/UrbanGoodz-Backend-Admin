<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Session 5 live-test readiness fix.
 *
 * The original foundation/manifest migrations added these columns only when
 * urban_goodz_route_packages already existed. Due to migration re-run ordering
 * in some environments, the base table was (re)created AFTER those guarded
 * migrations ran, so the columns were silently skipped. This migration
 * idempotently restores the intended schema so the Business Portal route,
 * package, scan and manifest flows work. It changes schema only (no payout
 * logic, no AI, no public listings).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_route_packages')) {
            return;
        }

        Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_route_packages', 'manifest_id')) {
                $table->unsignedBigInteger('manifest_id')->nullable()->after('business_client_id');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'dropoff_city')) {
                $table->string('dropoff_city', 255)->nullable()->after('dropoff_address');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'dropoff_state')) {
                $table->string('dropoff_state', 255)->nullable()->after('dropoff_city');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'dropoff_zip')) {
                $table->string('dropoff_zip', 20)->nullable()->after('dropoff_state');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'stop_order')) {
                $table->integer('stop_order')->default(0)->after('dropoff_zip');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'delivery_result')) {
                $table->string('delivery_result', 100)->nullable()->after('status');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'delivered_to_name')) {
                $table->string('delivered_to_name', 255)->nullable()->after('delivery_result');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'delivered_location_type')) {
                $table->string('delivered_location_type', 100)->nullable()->after('delivered_to_name');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'return_required')) {
                $table->boolean('return_required')->default(false)->after('delivered_location_type');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('return_required');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'return_location')) {
                $table->text('return_location')->nullable()->after('returned_at');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'geocode_status')) {
                $table->string('geocode_status', 50)->default('pending')->after('return_location');
            }
            if (!Schema::hasColumn('urban_goodz_route_packages', 'geocode_confidence')) {
                $table->decimal('geocode_confidence', 5, 2)->nullable()->after('geocode_status');
            }
        });

        if (Schema::hasColumn('urban_goodz_route_packages', 'manifest_id')
            && Schema::hasTable('urban_goodz_manifests')) {
            if (!$this->indexExists('urban_goodz_route_packages', 'ug_pkg_manifest_status_idx')) {
                Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                    $table->index(['manifest_id', 'status'], 'ug_pkg_manifest_status_idx');
                });
            }
            if (!$this->foreignKeyExists('urban_goodz_route_packages', 'ug_pkg_manifest_fk')) {
                try {
                    Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                        $table->foreign('manifest_id', 'ug_pkg_manifest_fk')
                              ->references('id')->on('urban_goodz_manifests')
                              ->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // FK is optional for functionality; ignore if it cannot be created.
                }
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: leave restored columns in place.
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $count = $connection->selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );
        return $count && (int) $count->c > 0;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $count = $connection->selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$database, $table, $constraint, 'FOREIGN KEY']
        );
        return $count && (int) $count->c > 0;
    }
};
