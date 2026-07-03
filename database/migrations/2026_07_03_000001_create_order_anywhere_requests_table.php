<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_anywhere_requests')) {
            return;
        }

        Schema::create('order_anywhere_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('store_vendor_name')->nullable();
            $table->text('store_vendor_address_or_website')->nullable();
            $table->text('request_details')->nullable();
            $table->text('item_details')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('budget_estimate', 12, 2)->nullable();
            $table->string('status')->default('pending_review')->index();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->string('vendor_status')->nullable()->index();
            $table->text('vendor_notes')->nullable();
            $table->decimal('vendor_quote_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('assigned_delivery_man_id')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('driver_task_status')->nullable();
            $table->text('driver_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_anywhere_requests');
    }
};
