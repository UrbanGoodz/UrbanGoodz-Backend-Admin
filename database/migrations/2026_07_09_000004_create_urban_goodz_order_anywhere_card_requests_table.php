<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_order_anywhere_card_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_anywhere_request_id')->nullable()->index('car_oa_req_idx');
            $table->unsignedBigInteger('delivery_man_id')->nullable()->index('car_driver_idx');
            $table->string('provider')->nullable()->index('car_provider_idx');
            $table->string('provider_card_id')->nullable();
            $table->string('provider_cardholder_id')->nullable();
            $table->string('provider_reference')->nullable()->index('car_prov_ref_idx');
            $table->string('card_status')->default('requested')->index('car_status_idx');
            $table->string('card_type')->default('virtual');
            $table->string('last4')->nullable();
            $table->decimal('spending_limit', 12, 2);
            $table->decimal('buffer_amount', 12, 2)->nullable();
            $table->string('currency', 8)->default('USD');
            $table->decimal('authorized_amount', 12, 2)->default(0);
            $table->decimal('captured_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('merchant_name')->nullable();
            $table->string('merchant_category_code')->nullable();
            $table->string('allowed_merchant')->nullable();
            $table->json('allowed_mccs')->nullable();
            $table->boolean('single_use')->default(true);
            $table->timestamp('usable_from')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_anywhere_request_id', 'card_status'], 'card_req_oa_status_idx');
            $table->index(['delivery_man_id', 'card_status'], 'card_req_driver_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_order_anywhere_card_requests');
    }
};
