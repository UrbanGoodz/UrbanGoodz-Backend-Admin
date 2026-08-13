<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('phone', 20);
                $table->string('email', 100)->nullable();
                $table->string('logo', 255)->nullable();
                $table->string('latitude', 50)->nullable();
                $table->string('longitude', 50)->nullable();
                $table->text('address')->nullable();
                $table->text('footer_text')->nullable();
                $table->decimal('minimum_order', 24, 3)->default(0);
                $table->decimal('comission', 24, 3)->nullable();
                $table->boolean('schedule_order')->default(false);
                $table->boolean('status')->default(true);
                $table->unsignedBigInteger('vendor_id')->index();
                $table->boolean('free_delivery')->default(false);
                $table->string('rating', 500)->nullable();
                $table->string('cover_photo', 255)->nullable();
                $table->boolean('delivery')->default(true);
                $table->boolean('take_away')->default(true);
                $table->boolean('item_section')->default(true);
                $table->decimal('tax', 24, 3)->default(0);
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->boolean('reviews_section')->default(true);
                $table->boolean('active')->default(true);
                $table->string('off_day', 255)->nullable();
                $table->string('gst', 500)->nullable();
                $table->boolean('self_delivery_system')->default(false);
                $table->boolean('pos_system')->default(false);
                $table->decimal('minimum_shipping_charge', 24, 3)->default(0);
                $table->string('delivery_time', 50)->nullable();
                $table->boolean('veg')->default(false);
                $table->boolean('non_veg')->default(false);
                $table->integer('order_count')->default(0);
                $table->integer('total_order')->default(0);
                $table->unsignedBigInteger('module_id')->nullable()->index();
                $table->string('pickup_zone_id', 500)->nullable();
                $table->integer('order_place_to_schedule_interval')->default(0);
                $table->boolean('featured')->default(false);
                $table->decimal('per_km_shipping_charge', 24, 3)->default(0);
                $table->boolean('prescription_order')->default(false);
                $table->string('slug', 255)->nullable()->unique();
                $table->decimal('maximum_shipping_charge', 24, 3)->nullable();
                $table->boolean('cutlery')->default(false);
                $table->string('meta_title', 255)->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->string('meta_image', 255)->nullable();
                $table->boolean('announcement')->default(false);
                $table->text('announcement_message')->nullable();
                $table->text('comment')->nullable();
                $table->string('tin', 50)->nullable();
                $table->date('tin_expire_date')->nullable();
                $table->string('tin_certificate_image', 255)->nullable();
                $table->string('business_status', 50)->default('active_partner');
                $table->string('contract_status', 50)->default('contracted');
                $table->string('vendor_admin_status', 50)->default('active');
                $table->string('banking_status', 50)->default('active');
                $table->string('subscription_status', 50)->default('active');
                $table->string('admin_approval_status', 50)->default('approved');
                $table->string('badge_status', 50)->nullable();
                $table->boolean('is_public_sourced')->default(false);
                $table->boolean('is_claimed')->default(false);
                $table->boolean('is_partner')->default(false);
                $table->boolean('can_direct_checkout')->default(true);
                $table->boolean('requires_admin_quote')->default(false);
                $table->boolean('vendor_admin_account_created')->default(false);
                $table->boolean('vendor_has_logged_in')->default(false);
                $table->boolean('partner_badge_enabled')->default(true);
                $table->boolean('order_anywhere_enabled')->default(true);
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('vendor_panel_activated_at')->nullable();
                $table->timestamp('banking_submitted_at')->nullable();
                $table->timestamp('banking_verified_at')->nullable();
                $table->timestamp('contracted_at')->nullable();
                $table->timestamp('subscription_activated_at')->nullable();
                $table->timestamp('admin_approved_at')->nullable();
                $table->timestamp('partner_badge_enabled_at')->nullable();
                $table->string('store_business_model', 50)->default('commission');
                $table->string('business_type_slug', 50)->nullable()->index();
                $table->string('fulfillment_mode', 50)->nullable();
                $table->integer('package_id')->nullable()->index();
                $table->json('meta_data')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};