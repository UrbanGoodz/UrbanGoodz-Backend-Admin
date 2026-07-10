<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_route_packages', 'age_restricted')) {
                    $table->boolean('age_restricted')->default(false)->after('temperature_requirement');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'requires_id_verification')) {
                    $table->boolean('requires_id_verification')->default(false)->after('age_restricted');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'no_contactless_delivery')) {
                    $table->boolean('no_contactless_delivery')->default(false)->after('requires_id_verification');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'delivery_completion_locked_until_verified')) {
                    $table->boolean('delivery_completion_locked_until_verified')->default(false)->after('no_contactless_delivery');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'admin_review_required_on_failure')) {
                    $table->boolean('admin_review_required_on_failure')->default(false)->after('delivery_completion_locked_until_verified');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'age_verification_status')) {
                    $table->string('age_verification_status', 50)->nullable()->after('admin_review_required_on_failure');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'age_verification_refusal_reason')) {
                    $table->string('age_verification_refusal_reason', 100)->nullable()->after('age_verification_status');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'age_verification_driver_notes')) {
                    $table->text('age_verification_driver_notes')->nullable()->after('age_verification_refusal_reason');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'age_verified_at')) {
                    $table->timestamp('age_verified_at')->nullable()->after('age_verification_driver_notes');
                }
                if (!Schema::hasColumn('urban_goodz_route_packages', 'age_verified_by_driver_id')) {
                    $table->unsignedBigInteger('age_verified_by_driver_id')->nullable()->after('age_verified_at');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_dedicated_routes', 'contains_age_restricted_items')) {
                    $table->boolean('contains_age_restricted_items')->default(false)->after('admin_notes');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'age_restricted_order')) {
                    $table->boolean('age_restricted_order')->default(false)->after('prescription_order');
                }
                if (!Schema::hasColumn('orders', 'age_verification_status')) {
                    $table->string('age_verification_status', 50)->nullable()->after('age_restricted_order');
                }
            });
        }

        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                if (!Schema::hasColumn('items', 'age_restricted')) {
                    $table->boolean('age_restricted')->default(false)->after('is_halal');
                }
                if (!Schema::hasColumn('items', 'age_restricted_type')) {
                    $table->string('age_restricted_type', 50)->nullable()->after('age_restricted');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_route_packages')) {
            Schema::table('urban_goodz_route_packages', function (Blueprint $table) {
                $cols = [
                    'age_restricted', 'requires_id_verification', 'no_contactless_delivery',
                    'delivery_completion_locked_until_verified', 'admin_review_required_on_failure',
                    'age_verification_status', 'age_verification_refusal_reason',
                    'age_verification_driver_notes', 'age_verified_at', 'age_verified_by_driver_id',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('urban_goodz_route_packages', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('urban_goodz_dedicated_routes')) {
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
                if (Schema::hasColumn('urban_goodz_dedicated_routes', 'contains_age_restricted_items')) {
                    $table->dropColumn('contains_age_restricted_items');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $cols = ['age_restricted_order', 'age_verification_status'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                $cols = ['age_restricted', 'age_restricted_type'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
