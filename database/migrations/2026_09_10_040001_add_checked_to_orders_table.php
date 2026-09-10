<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores the orders.checked notification flag that OrderController writes
 * (list + details mark previously unseen orders) but that no migration ever
 * created in freshly-built schemas. Existing deployments that already carry
 * the historical column are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'checked')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->tinyInteger('checked')->default(0)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'checked')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('checked');
            });
        }
    }
};