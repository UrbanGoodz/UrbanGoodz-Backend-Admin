<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_anywhere_requests')) {
            return;
        }

        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('order_anywhere_requests', 'business_id')) {
                $table->unsignedBigInteger('business_id')->nullable();
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable();
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'cart_items')) {
                $table->json('cart_items')->nullable();
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'source_urls')) {
                $table->json('source_urls')->nullable();
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'selected_options')) {
                $table->json('selected_options')->nullable();
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'customer_visible_status')) {
                $table->string('customer_visible_status')->default('confirming_availability');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'fulfillment_mode')) {
                $table->string('fulfillment_mode')->default('order_anywhere_backend');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_anywhere_requests')) {
            return;
        }

        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (Schema::hasColumn('order_anywhere_requests', 'business_id')) {
                $table->dropColumn('business_id');
            }
            if (Schema::hasColumn('order_anywhere_requests', 'product_id')) {
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('order_anywhere_requests', 'cart_items')) {
                $table->dropColumn('cart_items');
            }
            if (Schema::hasColumn('order_anywhere_requests', 'source_urls')) {
                $table->dropColumn('source_urls');
            }
            if (Schema::hasColumn('order_anywhere_requests', 'selected_options')) {
                $table->dropColumn('selected_options');
            }
            if (Schema::hasColumn('order_anywhere_requests', 'customer_visible_status')) {
                $table->dropColumn('customer_visible_status');
            }
            if (Schema::hasColumn('order_anywhere_requests', 'fulfillment_mode')) {
                $table->dropColumn('fulfillment_mode');
            }
        });
    }
};
