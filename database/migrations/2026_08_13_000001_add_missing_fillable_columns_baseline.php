<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes gaps found by a full model-instantiation sweep: for each table
 * below, a live, actively-used code path (verified by grep, not assumed)
 * writes or reads a column that is declared in the model's $fillable but
 * was never added by any migration. Each of these is a genuinely broken
 * runtime path today (Eloquent mass-assigns the column, MySQL rejects the
 * INSERT/UPDATE with "Unknown column").
 *
 * - zones.store_wise_topic / customer_wise_topic: read in push-notification
 *   topic targeting alongside the already-present deliveryman_wise_topic
 *   and rider_wise_topic columns.
 * - add_ons.store_id / status: StoreScope (vendor auth) filters add_ons by
 *   store_id; AddOn::scopeActive() filters by status.
 * - banners.type/data/zone_id/module_id/featured/time_period/start_date/
 *   end_date: Banner model's zone()/module() relations, scopeModule(),
 *   scopeFeatured(), and casts all reference these columns; the existing
 *   create_banners_table migration built a different (resource_type/
 *   resource_id/link/position) banner design that the model does not use.
 * - modules.thumbnail / stores_count: referenced by Module model casts and
 *   admin module listing views.
 * - order_anywhere_requests.driver_started_at / driver_completed_at:
 *   written by UrbanGoodzDriverActiveJobsController when a driver starts
 *   or completes a task.
 * - urban_goodz_medical_courier_custody_logs.handler_role/handler_id/
 *   signature_path: written by UrbanGoodzMedicalCourierService when
 *   logging chain-of-custody events.
 *
 * All columns are added nullable (even where a reference schema shows
 * NOT NULL) so this is safe to run against installations that already
 * have rows in these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            if (!Schema::hasColumn('zones', 'store_wise_topic')) {
                $table->string('store_wise_topic')->nullable()->after('deliveryman_wise_topic');
            }
            if (!Schema::hasColumn('zones', 'customer_wise_topic')) {
                $table->string('customer_wise_topic')->nullable()->after('deliveryman_wise_topic');
            }
        });

        Schema::table('add_ons', function (Blueprint $table) {
            if (!Schema::hasColumn('add_ons', 'store_id')) {
                $table->unsignedBigInteger('store_id')->nullable()->after('item_id');
            }
            if (!Schema::hasColumn('add_ons', 'status')) {
                $table->boolean('status')->default(true)->after('price');
            }
        });

        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'type')) {
                $table->string('type', 255)->nullable()->after('title');
            }
            if (!Schema::hasColumn('banners', 'data')) {
                $table->string('data', 255)->nullable()->after('image');
            }
            if (!Schema::hasColumn('banners', 'zone_id')) {
                $table->unsignedBigInteger('zone_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('banners', 'module_id')) {
                $table->unsignedBigInteger('module_id')->nullable()->after('zone_id');
            }
            if (!Schema::hasColumn('banners', 'featured')) {
                $table->boolean('featured')->default(false)->after('module_id');
            }
            if (!Schema::hasColumn('banners', 'time_period')) {
                $table->string('time_period')->nullable()->after('featured');
            }
            if (!Schema::hasColumn('banners', 'start_date')) {
                $table->date('start_date')->nullable()->after('time_period');
            }
            if (!Schema::hasColumn('banners', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        Schema::table('modules', function (Blueprint $table) {
            if (!Schema::hasColumn('modules', 'thumbnail')) {
                $table->string('thumbnail')->nullable()->after('module_type');
            }
            if (!Schema::hasColumn('modules', 'stores_count')) {
                $table->integer('stores_count')->default(0)->after('status');
            }
        });

        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_anywhere_requests', 'driver_started_at')) {
                $table->timestamp('driver_started_at')->nullable();
            }
            if (!Schema::hasColumn('order_anywhere_requests', 'driver_completed_at')) {
                $table->timestamp('driver_completed_at')->nullable();
            }
        });

        Schema::table('urban_goodz_medical_courier_custody_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_medical_courier_custody_logs', 'handler_role')) {
                $table->string('handler_role')->nullable()->after('handler_name');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_custody_logs', 'handler_id')) {
                $table->unsignedBigInteger('handler_id')->nullable()->after('handler_role');
            }
            if (!Schema::hasColumn('urban_goodz_medical_courier_custody_logs', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('handler_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('urban_goodz_medical_courier_custody_logs', function (Blueprint $table) {
            $table->dropColumn(['handler_role', 'handler_id', 'signature_path']);
        });

        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $table->dropColumn(['driver_started_at', 'driver_completed_at']);
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['thumbnail', 'stores_count']);
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['type', 'data', 'zone_id', 'module_id', 'featured', 'time_period', 'start_date', 'end_date']);
        });

        Schema::table('add_ons', function (Blueprint $table) {
            $table->dropColumn(['store_id', 'status']);
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['store_wise_topic', 'customer_wise_topic']);
        });
    }
};
