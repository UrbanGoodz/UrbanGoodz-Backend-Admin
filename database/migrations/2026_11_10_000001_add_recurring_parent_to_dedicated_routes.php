<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring routes: each generated run points at the template route it was copied from, and a
 * template can have at most one run per date, so the generator is safe to run any number of times.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urban_goodz_dedicated_routes') || Schema::hasColumn('urban_goodz_dedicated_routes', 'recurring_parent_id')) {
            return;
        }

        Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
            $table->unsignedBigInteger('recurring_parent_id')->nullable()->after('intake_batch_id')->index();
            $table->unique(['recurring_parent_id', 'scheduled_date'], 'ug_route_recur_parent_date_uniq');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('urban_goodz_dedicated_routes', 'recurring_parent_id')) {
            Schema::table('urban_goodz_dedicated_routes', function (Blueprint $table) {
                $table->dropUnique('ug_route_recur_parent_date_uniq');
                $table->dropColumn('recurring_parent_id');
            });
        }
    }
};
