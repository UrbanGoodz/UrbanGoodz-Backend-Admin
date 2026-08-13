<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->unsignedBigInteger('delivery_man_id')->nullable()->index();
                $table->unsignedBigInteger('module_id')->nullable()->index();
                $table->unsignedBigInteger('dm_vehicle_id')->nullable()->index();
                $table->string('order_type', 50)->default('delivery');
                $table->string('order_status', 50)->default('pending');
                $table->decimal('order_amount', 24, 3)->default(0);
                $table->decimal('coupon_discount_amount', 24, 3)->default(0);
                $table->decimal('total_tax_amount', 24, 3)->default(0);
                $table->decimal('store_discount_amount', 24, 3)->default(0);
                $table->decimal('flash_admin_discount_amount', 24, 3)->default(0);
                $table->decimal('flash_store_discount_amount', 24, 3)->default(0);
                $table->decimal('delivery_charge', 24, 3)->default(0);
                $table->decimal('additional_charge', 24, 3)->default(0);
                $table->decimal('original_delivery_charge', 24, 3)->default(0);
                $table->decimal('extra_packaging_amount', 24, 3)->default(0);
                $table->decimal('distance', 24, 3)->default(0);
                $table->decimal('tax_percentage', 24, 3)->default(0);
                $table->decimal('dm_tips', 24, 2)->default(0);
                $table->decimal('ref_bonus_amount', 24, 3)->default(0);
                $table->decimal('service_charge', 24, 3)->default(0);
                $table->decimal('processing_time', 24, 3)->default(0);
                $table->integer('details_count')->default(0);
                $table->integer('scheduled')->default(0);
                $table->dateTime('schedule_at')->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('transaction_reference', 100)->nullable();
                $table->string('coupon_code', 50)->nullable();
                $table->string('order_attachment', 1000)->nullable();
                $table->string('order_proof', 1000)->nullable();
                $table->text('delivery_instruction')->nullable();
                $table->text('delivery_address')->nullable();
                $table->text('receiver_details')->nullable();
                $table->string('coupon_discount_data', 1000)->nullable();
                $table->string('ref_code', 20)->nullable();
                $table->string('promo_code', 50)->nullable();
                $table->boolean('prescription_order')->default(false);
                $table->boolean('cutlery')->default(false);
                $table->boolean('is_guest')->default(false);
                $table->boolean('bring_change_amount')->default(0);
                $table->boolean('age_restricted_order')->default(false);
                $table->dateTime('customer_age_confirmed_at')->nullable();
                $table->string('order_note', 1000)->nullable();
                $table->integer('vendor_id')->nullable()->index();
                $table->string('order_code', 50)->nullable()->unique();
                $table->string('branch_id', 50)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};