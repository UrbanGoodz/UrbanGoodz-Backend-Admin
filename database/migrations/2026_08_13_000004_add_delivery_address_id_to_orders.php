<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * app/Models/Order.php casts delivery_address_id as an integer, but no
 * migration anywhere ever added the column - surfaced by
 * UrbanGoodzAiAuditTest inserting orders through the model. Consistent
 * with the rest of `orders` (which has no FK constraints by original
 * design), added unconstrained and nullable.
 *
 * orders.callback: PaymentController::success/fail/cancel reads
 * $order->callback to redirect the customer back to the calling app after
 * payment (app/Http/Controllers/PaymentController.php:142-160), and it is
 * written via Order::where(...)->update(['callback' => $request['callback']])
 * at PaymentController.php:50. This column never existed, so every
 * payment-callback redirect has been silently failing (Eloquent returns
 * null for a missing attribute instead of erroring, so this had no visible
 * symptom until a test tried to write it directly). AiCopilotService
 * treats the string 'default' as a sentinel meaning "no external callback
 * configured", hence the default below.
 *
 * delivery_men.assigned_order_count: incremented/decremented in at least 6
 * real dispatch code paths (Admin\OrderController, Api\V1\DeliverymanController,
 * AiCopilotService, OrderAnywhereDispatchIntegrationService,
 * UrbanGoodz\SupportAIService) alongside the sibling column current_orders,
 * which does exist. Never had a migration, so every one of those increments
 * has been throwing - in AiCopilotService::autoDispatchOrder specifically,
 * the exception is caught and the whole auto-dispatch transaction silently
 * rolls back and reports failure, meaning AI auto-dispatch has never
 * actually assigned a driver in this environment.
 *
 * order_transactions.store_amount: the core "what the vendor earns on this
 * order" figure, written in app/CentralLogics/OrderLogic.php and read
 * throughout vendor earnings/reporting (StoreLogic::monthly/weekly/daily
 * earning sums, Admin\ReportController, vendor dashboard). Never had a
 * migration despite every sibling money column (commission,
 * delivery_charge, admin_commission, vendor_earning, ...) existing.
 *
 * order_transactions.order_amount: written alongside store_amount at the
 * same OrderLogic.php call sites (a snapshot of the order's total at
 * settlement time), read by the admin dashboard's revenue aggregate.
 *
 * reviews.status: Review::scopeActive() filters on it
 * (where('status', 1)), and Helpers::store_data_formatting()'s
 * hasManyThrough average-rating query for every store listing joins on it
 * unconditionally - so this broke store rating display everywhere a store
 * is formatted for an API response, not just one endpoint. Modeled as a
 * moderation flag (visible by default) to match the scope's semantics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_address_id')) {
                $table->unsignedBigInteger('delivery_address_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'callback')) {
                $table->string('callback', 500)->default('default');
            }
        });

        Schema::table('delivery_men', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_men', 'assigned_order_count')) {
                $table->integer('assigned_order_count')->default(0)->after('current_orders');
            }
        });

        Schema::table('order_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('order_transactions', 'store_amount')) {
                $table->decimal('store_amount', 24, 3)->default(0)->after('vendor_earning');
            }
            if (!Schema::hasColumn('order_transactions', 'order_amount')) {
                $table->decimal('order_amount', 24, 3)->default(0)->after('store_amount');
            }
        });

        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'status')) {
                $table->boolean('status')->default(1)->after('rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn(['store_amount', 'order_amount']);
        });

        Schema::table('delivery_men', function (Blueprint $table) {
            $table->dropColumn('assigned_order_count');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_address_id', 'callback']);
        });
    }
};
