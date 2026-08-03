<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a package scan into a full operational event.
 *
 * The table recorded who scanned what, where and when, but not the route or
 * stop it happened on, which business the package belonged to, what identifier
 * was presented, or what the package status was before and after. Without
 * `idempotency_key` there was also no way to satisfy two requirements at once:
 * a duplicate scan returning a clear idempotent result, and an offline scan
 * queue synchronising exactly once.
 *
 * `occurred_at` is the moment the scan happened on the device, which can be
 * well before `created_at` when a queued offline scan is flushed later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urban_goodz_package_scans')) {
            return;
        }

        $addIdempotencyKey = ! Schema::hasColumn('urban_goodz_package_scans', 'idempotency_key');

        Schema::table('urban_goodz_package_scans', function (Blueprint $table) {
            if (! Schema::hasColumn('urban_goodz_package_scans', 'route_id')) {
                $table->unsignedBigInteger('route_id')->nullable()->after('package_id');
            }
            if (! Schema::hasColumn('urban_goodz_package_scans', 'stop_id')) {
                $table->unsignedBigInteger('stop_id')->nullable()->after('route_id');
            }
            if (! Schema::hasColumn('urban_goodz_package_scans', 'business_client_id')) {
                $table->unsignedBigInteger('business_client_id')->nullable()->after('stop_id');
            }

            // barcode | qr | tracking_id | package_id | manual
            if (! Schema::hasColumn('urban_goodz_package_scans', 'identifier_type')) {
                $table->string('identifier_type')->nullable()->after('scanner_type');
            }
            if (! Schema::hasColumn('urban_goodz_package_scans', 'identifier_value')) {
                $table->string('identifier_value')->nullable()->after('identifier_type');
            }

            if (! Schema::hasColumn('urban_goodz_package_scans', 'status_before')) {
                $table->string('status_before')->nullable()->after('identifier_value');
            }
            if (! Schema::hasColumn('urban_goodz_package_scans', 'status_after')) {
                $table->string('status_after')->nullable()->after('status_before');
            }

            if (! Schema::hasColumn('urban_goodz_package_scans', 'proof_reference')) {
                $table->string('proof_reference')->nullable()->after('signature');
            }
            if (! Schema::hasColumn('urban_goodz_package_scans', 'device_source')) {
                $table->string('device_source')->nullable()->after('proof_reference');
            }

            if (! Schema::hasColumn('urban_goodz_package_scans', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('urban_goodz_package_scans', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('urban_goodz_package_scans', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('occurred_at');
            }
        });

        Schema::table('urban_goodz_package_scans', function (Blueprint $table) use ($addIdempotencyKey) {
            if ($addIdempotencyKey) {
                $table->unique('idempotency_key', 'ug_ps_idempotency_unique');
            }
            $table->index(['route_id', 'scan_type'], 'ug_ps_route_type_idx');
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive. These audit columns may predate this
        // release in production and deleting them would destroy scan history.
    }
};
