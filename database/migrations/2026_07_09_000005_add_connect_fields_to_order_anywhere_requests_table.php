<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_provider')) {
                $table->string('payment_provider')->nullable()->after('payment_url');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('payment_provider');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'provider_payment_id')) {
                $table->string('provider_payment_id')->nullable()->after('provider_reference');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'connect_account_id')) {
                $table->string('connect_account_id')->nullable()->after('provider_payment_id');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'payout_status')) {
                $table->string('payout_status')->default('manual_pending')->after('connect_account_id');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'transfer_status')) {
                $table->string('transfer_status')->nullable()->after('payout_status');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'transfer_reference')) {
                $table->string('transfer_reference')->nullable()->after('transfer_status');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'platform_fee')) {
                $table->decimal('platform_fee', 12, 2)->nullable()->after('transfer_reference');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'vendor_payout_amount')) {
                $table->decimal('vendor_payout_amount', 12, 2)->nullable()->after('platform_fee');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'driver_payout_amount')) {
                $table->decimal('driver_payout_amount', 12, 2)->nullable()->after('vendor_payout_amount');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_mode')) {
                $table->string('payment_mode')->nullable()->after('driver_payout_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $columns = [
                'payment_provider', 'provider_reference', 'provider_payment_id',
                'connect_account_id', 'payout_status', 'transfer_status', 'transfer_reference',
                'platform_fee', 'vendor_payout_amount', 'driver_payout_amount', 'payment_mode',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_anywhere_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
