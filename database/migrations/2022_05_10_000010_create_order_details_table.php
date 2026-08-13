<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_details')) {
            Schema::create('order_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('item_id')->nullable()->index();
                $table->unsignedBigInteger('item_campaign_id')->nullable()->index();
                $table->decimal('price', 24, 3)->default(0);
                $table->decimal('discount_on_item', 24, 3)->default(0);
                $table->decimal('total_add_on_price', 24, 3)->default(0);
                $table->decimal('tax_amount', 24, 3)->default(0);
                $table->integer('quantity')->default(1);
                $table->json('variation')->nullable();
                $table->json('add_ons')->nullable();
                $table->json('discount_data')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};