<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255);
                $table->string('code', 50)->unique();
                $table->dateTime('start_date')->nullable();
                $table->dateTime('expire_date')->nullable();
                $table->decimal('min_purchase', 24, 3)->default(0);
                $table->decimal('max_discount', 24, 3)->default(0);
                $table->decimal('discount', 24, 3)->default(0);
                $table->string('discount_type', 20)->default('percent');
                $table->string('coupon_type', 50)->default('all');
                $table->integer('limit')->nullable();
                $table->integer('status')->default(1);
                $table->text('data')->nullable();
                $table->integer('total_uses')->default(0);
                $table->unsignedBigInteger('module_id')->nullable()->index();
                $table->string('created_by', 50)->nullable();
                $table->string('customer_id', 50)->nullable();
                $table->string('slug', 255)->nullable()->unique();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};