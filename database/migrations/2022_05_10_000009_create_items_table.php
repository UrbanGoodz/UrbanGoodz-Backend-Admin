<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable()->index();
                $table->unsignedBigInteger('module_id')->nullable()->index();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('image', 255)->nullable();
                $table->json('images')->nullable();
                $table->decimal('price', 24, 3)->default(0);
                $table->decimal('tax', 24, 3)->default(0);
                $table->decimal('discount', 24, 3)->default(0);
                $table->string('discount_type', 20)->default('percent');
                $table->integer('status')->default(1);
                $table->integer('set_menu')->default(0);
                $table->integer('recommended')->default(0);
                $table->integer('maximum_cart_quantity')->default(0);
                $table->integer('organic')->default(0);
                $table->decimal('avg_rating', 24, 3)->default(0);
                $table->integer('reviews_count')->default(0);
                $table->integer('rating_count')->default(0);
                $table->integer('order_count')->default(0);
                $table->integer('is_approved')->default(1);
                $table->integer('stock')->default(0);
                $table->decimal('min_price', 24, 3)->default(0);
                $table->decimal('max_price', 24, 3)->default(0);
                $table->integer('veg')->default(1);
                $table->integer('is_halal')->default(0);
                $table->integer('age_restricted')->default(0);
                $table->string('available_time_starts', 10)->nullable();
                $table->string('available_time_ends', 10)->nullable();
                $table->string('slug', 255)->nullable()->unique();
                $table->string('video', 255)->nullable();
                $table->string('temp_product_id', 50)->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};