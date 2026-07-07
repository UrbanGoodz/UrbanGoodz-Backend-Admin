<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_anywhere_requests', 'delivery_man_id')) {
                $table->unsignedBigInteger('delivery_man_id')->nullable()->index()->after('assigned_delivery_man_id');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'driver_status')) {
                $table->string('driver_status')->nullable()->default('unassigned')->index()->after('delivery_man_id');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('driver_status');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'driver_accepted_at')) {
                $table->timestamp('driver_accepted_at')->nullable()->after('assigned_at');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'arrived_at_pickup_at')) {
                $table->timestamp('arrived_at_pickup_at')->nullable()->after('driver_accepted_at');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('arrived_at_pickup_at');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'out_for_delivery_at')) {
                $table->timestamp('out_for_delivery_at')->nullable()->after('picked_up_at');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('out_for_delivery_at');
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'driver_notes')) {
                $table->text('driver_notes')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $columns = [
                'delivery_man_id',
                'driver_status',
                'assigned_at',
                'driver_accepted_at',
                'arrived_at_pickup_at',
                'picked_up_at',
                'out_for_delivery_at',
                'delivered_at',
                'driver_notes',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_anywhere_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
