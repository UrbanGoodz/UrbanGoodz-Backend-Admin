<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_anywhere_requests', 'item_subtotal')) {
                $table->decimal('item_subtotal', 10, 2)->nullable()->after('budget_estimate');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'service_fee')) {
                $table->decimal('service_fee', 10, 2)->default(0)->after('item_subtotal');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'delivery_fee')) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('service_fee');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'tax')) {
                $table->decimal('tax', 10, 2)->default(0)->after('delivery_fee');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'tip')) {
                $table->decimal('tip', 10, 2)->default(0)->after('tax');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $columns = ['item_subtotal', 'service_fee', 'delivery_fee', 'tax', 'tip'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_anywhere_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
