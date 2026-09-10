<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores the withdraw_requests.approved tri-state column (0 pending,
 * 1 approved, 2 denied) that VendorController filters by and writes in
 * status updates, but that this repository's create-withdraw_requests
 * migration no longer defines. Existing deployments that already carry the
 * historical column are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('withdraw_requests', 'approved')) {
            Schema::table('withdraw_requests', function (Blueprint $table) {
                $table->tinyInteger('approved')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('withdraw_requests', 'approved')) {
            Schema::table('withdraw_requests', function (Blueprint $table) {
                $table->dropColumn('approved');
            });
        }
    }
};