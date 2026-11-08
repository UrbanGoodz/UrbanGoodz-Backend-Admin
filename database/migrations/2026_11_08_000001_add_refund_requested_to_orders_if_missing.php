<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The refund-request endpoint writes orders.refund_requested. The baseline SQL dump has the column
 * but no migration ever created it, so an environment built from migrations alone failed every
 * refund request with "Unknown column 'refund_requested'". Guarded: a no-op where it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'refund_requested')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('refund_requested')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Left in place: the column belongs to the baseline schema.
    }
};
