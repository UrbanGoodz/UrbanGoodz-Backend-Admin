<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_dispatches')) {
            return;
        }

        Schema::create('ai_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('source_type')->nullable();
            $table->bigInteger('source_id')->nullable()->unsigned();
            $table->bigInteger('load_id')->nullable()->unsigned();
            $table->bigInteger('route_id')->nullable()->unsigned();
            $table->bigInteger('order_id')->nullable()->unsigned();
            $table->bigInteger('business_client_id')->nullable()->unsigned();
            $table->bigInteger('vendor_id')->nullable()->unsigned();
            $table->bigInteger('customer_id')->nullable()->unsigned();
            $table->bigInteger('dispatcher_id')->nullable()->unsigned();
            $table->bigInteger('driver_id')->nullable()->unsigned();
            $table->bigInteger('delivery_man_id')->nullable()->unsigned();
            $table->string('created_by_type')->nullable();
            $table->bigInteger('created_by_id')->nullable()->unsigned();
            $table->boolean('recommended_by_ai')->default(false);
            $table->bigInteger('ai_recommendation_id')->nullable()->unsigned();
            $table->decimal('ai_match_score', 5, 2)->nullable();
            $table->text('ai_reasoning_summary')->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at_pickup_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('arrived_at_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('settlement_pending_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('exception_state')->nullable();
            $table->string('exception_type')->nullable();
            $table->text('exception_description')->nullable();
            $table->string('exception_resolved_by')->nullable();
            $table->string('decline_reason_code', 30)->nullable();
            $table->text('decline_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('safety_flags')->nullable();
            $table->decimal('driver_payout_amount', 10, 2)->nullable();
            $table->string('payout_currency', 3)->default('USD');
            $table->string('fulfillment_type', 30)->nullable();
            $table->boolean('push_sent')->default(false);
            $table->string('push_status')->nullable();
            $table->text('push_error')->nullable();
            $table->boolean('in_app_notified')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'delivery_man_id']);
            $table->index(['business_client_id', 'status']);
            $table->index(['load_id', 'status']);
            $table->index('dispatcher_id');
            $table->index('route_id');
            $table->index('order_id');
            $table->index('uuid');
            $table->index(['delivery_man_id', 'status', 'offer_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_dispatches');
    }
};
