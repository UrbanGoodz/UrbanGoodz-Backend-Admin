<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens driver compensation policies to the full resolution hierarchy.
 *
 * The table already carried every payout *method* the specification asks for
 * (flat, per mile, per minute, per stop, per package, percentage, plus
 * urgency/waiting/return/exception premiums and min/max guards) but could only
 * be narrowed by `policy_type` and `zone_id` — two of the thirteen levels. A
 * business, contract, route, vehicle type, load type or single assignment had
 * nowhere to express its own rate.
 *
 * Every column is nullable, so existing rows keep behaving exactly as before:
 * a NULL dimension is a wildcard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urban_goodz_driver_pricing_policies', function (Blueprint $table) {
            // 1 — assignment-specific approved rate
            $table->string('subject_type')->nullable()->after('policy_type');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');

            // 2 — contract
            $table->unsignedBigInteger('contract_id')->nullable()->after('subject_id');

            // 3/4 — dedicated and recurring routes
            $table->unsignedBigInteger('route_id')->nullable()->after('contract_id');
            $table->string('route_scope')->nullable()->after('route_id'); // dedicated | recurring

            // 5 — business client
            $table->unsignedBigInteger('business_client_id')->nullable()->after('route_scope');

            // 6/7/8/9 — service, vehicle, load and medical typing
            $table->string('service_type')->nullable()->after('business_client_id');
            $table->unsignedBigInteger('vehicle_type_id')->nullable()->after('service_type');
            $table->string('load_type')->nullable()->after('vehicle_type_id');
            $table->string('medical_type')->nullable()->after('load_type');

            // 10 — market sits alongside the existing zone_id
            $table->string('market')->nullable()->after('medical_type');

            // 11 — module default
            $table->unsignedBigInteger('module_id')->nullable()->after('market');

            // Tie-break inside a tier. Higher wins.
            $table->integer('priority')->default(0)->after('module_id');

            // Rule versioning, so an earning can name the exact revision used.
            $table->unsignedInteger('version')->default(1)->after('priority');

            $table->index(['subject_type', 'subject_id'], 'ug_dpp_subject_idx');
            $table->index(['business_client_id'], 'ug_dpp_business_idx');
            $table->index(['route_id'], 'ug_dpp_route_idx');
            $table->index(['service_type'], 'ug_dpp_service_idx');
            $table->index(['module_id'], 'ug_dpp_module_idx');
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_driver_pricing_policies', function (Blueprint $table) {
            $table->dropIndex('ug_dpp_subject_idx');
            $table->dropIndex('ug_dpp_business_idx');
            $table->dropIndex('ug_dpp_route_idx');
            $table->dropIndex('ug_dpp_service_idx');
            $table->dropIndex('ug_dpp_module_idx');

            $table->dropColumn([
                'subject_type', 'subject_id', 'contract_id', 'route_id', 'route_scope',
                'business_client_id', 'service_type', 'vehicle_type_id', 'load_type',
                'medical_type', 'market', 'module_id', 'priority', 'version',
            ]);
        });
    }
};
