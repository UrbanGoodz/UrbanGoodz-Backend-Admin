<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_transactions')) {
            Schema::create('order_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('delivery_man_id')->nullable()->index();
                $table->unsignedBigInteger('module_id')->nullable()->index();
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->decimal('commission', 24, 3)->default(0);
                $table->decimal('delivery_charge', 24, 3)->default(0);
                $table->decimal('admin_commission', 24, 3)->default(0);
                $table->decimal('vendor_earning', 24, 3)->default(0);
                $table->decimal('delivery_man_earning', 24, 3)->default(0);
                $table->decimal('dm_tips', 24, 2)->default(0);
                $table->decimal('expense', 24, 3)->default(0);
                $table->decimal('delivery_fee_commission', 24, 3)->default(0);
                $table->decimal('store_expense', 24, 3)->default(0);
                $table->decimal('discount_amount_by_store', 24, 3)->default(0);
                $table->decimal('subscription_model', 24, 3)->default(0);
                $table->string('status', 50)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_transactions');
    }
};