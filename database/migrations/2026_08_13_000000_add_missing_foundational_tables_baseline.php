<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative baseline for foundational tables that ship with the base
 * 6amtech product installer (raw SQL import) rather than a Laravel
 * migration, and therefore have no CREATE migration anywhere in this
 * repository. A full model-instantiation sweep against a genuine
 * from-scratch `migrate` run confirmed these 17 tables are required by
 * existing Eloquent models but are never created by any migration.
 *
 * Column definitions below were sourced from
 * database/baseline/urbangoodz_candidate_schema.sql via `SHOW CREATE TABLE`
 * against an isolated scratch import. That file is explicitly documented
 * (database/baseline/SCHEMA_SOURCE_REPORT.md) as a CANDIDATE SCHEMA
 * BASELINE with unverified production provenance. It is used here only
 * because it is the sole available source for these tables and inventing
 * columns from nothing would be strictly worse; every table is guarded
 * with hasTable() so this is a no-op wherever the table already exists
 * (e.g. installations that ran the base product installer).
 *
 * payment_requests and addon_settings had no PRIMARY KEY in the candidate
 * dump despite a NOT NULL char(36) id column. Both models (PaymentRequest,
 * Setting) rely on `id` as a unique key (HasUuid trait / UUID PK usage),
 * so a PRIMARY KEY on `id` is added here as a correctness fix, not a
 * literal copy of the candidate dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_roles')) {
            Schema::create('admin_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('modules')->nullable();
                $table->boolean('status')->default(1);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('attributes')) {
            Schema::create('attributes', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('country')->nullable();
                $table->string('currency_code')->nullable();
                $table->string('currency_symbol')->nullable();
                $table->decimal('exchange_rate', 8, 2)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('delivery_histories')) {
            Schema::create('delivery_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('delivery_man_id')->nullable();
                $table->dateTime('time')->nullable();
                $table->string('longitude')->nullable();
                $table->string('latitude')->nullable();
                $table->string('location')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('delivery_man_wallets')) {
            Schema::create('delivery_man_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_man_id');
                $table->decimal('collected_cash', 24, 2)->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->decimal('total_earning', 24, 2)->default(0);
                $table->decimal('total_withdrawn', 24, 2)->default(0);
                $table->decimal('pending_withdraw', 24, 2)->default(0);
            });
        }

        if (!Schema::hasTable('discounts')) {
            Schema::create('discounts', function (Blueprint $table) {
                $table->id();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->decimal('min_purchase', 24, 2)->default(0);
                $table->decimal('max_discount', 24, 2)->default(0);
                $table->decimal('discount', 24, 2)->default(0);
                $table->string('discount_type', 15)->default('percentage');
                $table->unsignedBigInteger('store_id');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('employee_roles')) {
            Schema::create('employee_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('modules')->nullable();
                $table->boolean('status')->default(1);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
            });
        }

        if (!Schema::hasTable('module_types')) {
            Schema::create('module_types', function (Blueprint $table) {
                $table->id();
                $table->string('type', 191)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->string('title', 191)->nullable();
                $table->text('description')->nullable();
                $table->string('image', 50)->nullable();
                $table->boolean('status')->default(1);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('tergat')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
            });
        }

        if (!Schema::hasTable('order_delivery_histories')) {
            Schema::create('order_delivery_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('delivery_man_id')->nullable();
                $table->dateTime('start_time')->nullable();
                $table->dateTime('end_time')->nullable();
                $table->string('start_location')->nullable();
                $table->string('end_location')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('payment_requests')) {
            Schema::create('payment_requests', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('payer_id', 64)->nullable();
                $table->string('receiver_id', 64)->nullable();
                $table->decimal('payment_amount', 24, 2)->default(0);
                $table->string('gateway_callback_url', 191)->nullable();
                $table->string('success_hook', 100)->nullable();
                $table->string('failure_hook', 100)->nullable();
                $table->string('transaction_id', 100)->nullable();
                $table->string('currency_code', 20)->default('USD');
                $table->string('payment_method', 50)->nullable();
                $table->longText('additional_data')->collation('utf8mb4_bin')->nullable();
                $table->boolean('is_paid')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->longText('payer_information')->collation('utf8mb4_bin')->nullable();
                $table->string('external_redirect_link')->nullable();
                $table->longText('receiver_information')->collation('utf8mb4_bin')->nullable();
                $table->string('attribute_id', 64)->nullable();
                $table->string('attribute')->nullable();
                $table->string('payment_platform')->nullable();
            });
        }

        if (!Schema::hasTable('provide_d_m_earnings')) {
            Schema::create('provide_d_m_earnings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_man_id');
                $table->decimal('amount', 24, 2)->default(0);
                $table->string('method')->nullable();
                $table->string('ref')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('addon_settings')) {
            Schema::create('addon_settings', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('key_name', 191)->nullable();
                $table->longText('live_values')->nullable();
                $table->longText('test_values')->nullable();
                $table->string('settings_type')->nullable();
                $table->string('mode', 20)->default('live');
                $table->boolean('is_active')->default(1);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->longText('additional_data')->collation('utf8mb4_bin')->nullable();
                $table->index('id', 'payment_settings_id_index');
            });
        }

        if (!Schema::hasTable('store_schedule')) {
            Schema::create('store_schedule', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id');
                $table->integer('day');
                $table->time('opening_time')->nullable();
                $table->time('closing_time')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('store_wallets')) {
            Schema::create('store_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->decimal('total_earning', 24, 2)->default(0);
                $table->decimal('total_withdrawn', 24, 2)->default(0);
                $table->decimal('pending_withdraw', 24, 2)->default(0);
                $table->decimal('collected_cash', 24, 2)->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('track_deliverymen')) {
            Schema::create('track_deliverymen', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('delivery_man_id')->nullable();
                $table->string('longitude', 20)->nullable();
                $table->string('latitude', 20)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('unit', 191);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
        Schema::dropIfExists('track_deliverymen');
        Schema::dropIfExists('store_wallets');
        Schema::dropIfExists('store_schedule');
        Schema::dropIfExists('addon_settings');
        Schema::dropIfExists('provide_d_m_earnings');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('order_delivery_histories');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('module_types');
        Schema::dropIfExists('employee_roles');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('delivery_man_wallets');
        Schema::dropIfExists('delivery_histories');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('admin_roles');
    }
};
