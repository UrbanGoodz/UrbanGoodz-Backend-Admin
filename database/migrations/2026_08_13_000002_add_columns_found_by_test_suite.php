<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes genuine schema gaps surfaced by actually running the test suite
 * (php artisan test) against a fresh install, not by static analysis.
 * Each of these bypasses $fillable (direct property assignment, raw
 * DB::table() calls, or query-builder aggregates), which is why the
 * earlier model/fillable-based sweep could not detect them; only
 * exercising real request/response cycles surfaced them.
 *
 * - orders.confirmed / orders.accepted: written via direct property
 *   assignment ($order->confirmed = now(); / $order->accepted = now();)
 *   in app/Http/Controllers/Admin/OrderController.php and
 *   app/Http/Controllers/Api/V1/DeliverymanController.php, and read back
 *   by app/Http/Controllers/Api/V1/UrbanGoodz/OrderAiDispatchAdminController.php
 *   to find unaccepted orders.
 * - orders.payment_status: read/written throughout order, payment and
 *   webhook flows ($order->payment_status) but never present on the
 *   orders table itself (distinct from the unrelated payment_status
 *   columns already correctly defined on order_anywhere_requests,
 *   order_payments, etc.).
 * - items.tax_type: read in app/CentralLogics/Helpers.php
 *   ($item['tax_type'] == 'percent') for tax calculation, sitting
 *   alongside the already-present tax/discount/discount_type columns on
 *   the same table.
 * - users.cm_firebase_token: the CREATE migration that defines this
 *   column (2022_05_09_235958_create_core_users_and_vendors_tables_if_missing.php)
 *   is wrapped in `if (!Schema::hasTable('users'))`, so it never runs
 *   against an install whose `users` table already exists from elsewhere
 *   in the chain — the same "if-missing baseline never applies" pattern
 *   already fixed for whole tables, here affecting one column.
 * - admin_wallets.total_commission_earning: incremented directly via
 *   query builder in the payment capture path; the table only ever
 *   gained `balance` and `delivery_charge`.
 * - wishlists.store_id (plus making item_id nullable): WishlistController
 *   implements both item-wishlisting and store-wishlisting
 *   ('item_id' => 'required_without:store_id' /
 *   'store_id' => 'required_without:item_id'), and the Wishlist model
 *   already declares a store() relation, but the table was only ever
 *   built for items (item_id NOT NULL, no store_id column at all).
 *
 * withdraw_requests.approved and delivery_men.fcm_token (also seen as
 * "Unknown column" test failures) are deliberately NOT added here:
 * - withdraw_requests already has the authoritative `status` varchar
 *   column that every real controller uses for withdrawal state.
 *   `approved` appears only in two tests and one already defensively
 *   guarded diagnostic query (AiChiefOfStaffService::countWhen, which
 *   checks Schema::hasColumn before querying and degrades gracefully).
 *   Adding a redundant boolean would invent a second, competing source
 *   of truth for withdrawal state — a test defect, not a schema gap.
 * - delivery_men already has `firebase_token`; the one caller that read
 *   `fcm_token` (app/Services/UrbanGoodzNotificationService.php) was a
 *   column-name bug fixed directly in that file, not a schema gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'confirmed')) {
                $table->timestamp('confirmed')->nullable();
            }
            if (!Schema::hasColumn('orders', 'accepted')) {
                $table->timestamp('accepted')->nullable();
            }
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 30)->default('unpaid')->after('payment_method');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'tax_type')) {
                $table->string('tax_type', 20)->default('percent')->after('tax');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'cm_firebase_token')) {
                $table->string('cm_firebase_token', 255)->nullable();
            }
        });

        Schema::table('admin_wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_wallets', 'total_commission_earning')) {
                $table->decimal('total_commission_earning', 24, 3)->default(0)->after('delivery_charge');
            }
        });

        Schema::table('wishlists', function (Blueprint $table) {
            if (!Schema::hasColumn('wishlists', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable()->after('item_id');
            }
        });
        if (Schema::hasColumn('wishlists', 'item_id')) {
            Schema::table('wishlists', function (Blueprint $table) {
                $table->unsignedBigInteger('item_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('store_id');
        });

        Schema::table('admin_wallets', function (Blueprint $table) {
            $table->dropColumn('total_commission_earning');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cm_firebase_token');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('tax_type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['confirmed', 'accepted', 'payment_status']);
        });
    }
};
