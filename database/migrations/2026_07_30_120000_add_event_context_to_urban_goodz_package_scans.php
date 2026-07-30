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
        Schema::table('urban_goodz_package_scans', function (Blueprint $table) {
            $table->unsignedBigInteger('route_id')->nullable()->after('package_id');
            $table->unsignedBigInteger('stop_id')->nullable()->after('route_id');
            $table->unsignedBigInteger('business_client_id')->nullable()->after('stop_id');

            // barcode | qr | tracking_id | package_id | manual
            $table->string('identifier_type')->nullable()->after('scanner_type');
            $table->string('identifier_value')->nullable()->after('identifier_type');

            $table->string('status_before')->nullable()->after('identifier_value');
            $table->string('status_after')->nullable()->after('status_before');

            $table->string('proof_reference')->nullable()->after('signature');
            $table->string('device_source')->nullable()->after('proof_reference');

            $table->json('metadata')->nullable()->after('notes');

            $table->timestamp('occurred_at')->nullable()->after('metadata');
            $table->string('idempotency_key')->nullable()->after('occurred_at');

            $table->unique('idempotency_key', 'ug_ps_idempotency_unique');
            $table->index(['route_id', 'scan_type'], 'ug_ps_route_type_idx');
            $table->index('business_client_id', 'ug_ps_business_idx');
            $table->index('package_id', 'ug_ps_package_idx');
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_package_scans', function (Blueprint $table) {
            $table->dropUnique('ug_ps_idempotency_unique');
            $table->dropIndex('ug_ps_route_type_idx');
            $table->dropIndex('ug_ps_business_idx');
            $table->dropIndex('ug_ps_package_idx');

            $table->dropColumn([
                'route_id', 'stop_id', 'business_client_id',
                'identifier_type', 'identifier_value',
                'status_before', 'status_after',
                'proof_reference', 'device_source',
                'metadata', 'occurred_at', 'idempotency_key',
            ]);
        });
    }
};
