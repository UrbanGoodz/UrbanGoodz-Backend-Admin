<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            // Fulfillment type: external_merchant | participating_vendor
            $table->string('fulfillment_type', 32)->default('external_merchant')->after('status');

            // Sourcing tracking
            $table->string('sourcing_status', 32)->nullable()->after('fulfillment_type');

            // Shopper/driver for external merchant path
            $table->unsignedBigInteger('shopper_id')->nullable()->after('vendor_id');
            $table->string('shopper_status', 32)->default('unassigned')->after('shopper_id');

            // Card issuing tracking (separate from customer payment)
            $table->boolean('card_issued')->default(false)->after('shopper_status');
            $table->unsignedBigInteger('card_request_id')->nullable()->after('card_issued');

            // Authorization expiry
            $table->timestamp('authorization_expires_at')->nullable()->after('payment_refunded_at');

            // Receipt and reconciliation
            $table->decimal('receipt_amount', 12, 2)->nullable()->after('receipt_path');
            $table->decimal('receipt_difference', 12, 2)->nullable()->after('receipt_amount');
            $table->string('receipt_image_path')->nullable()->after('receipt_difference');
            $table->text('receipt_notes')->nullable()->after('receipt_image_path');
            $table->string('reconciliation_status', 32)->default('pending')->after('receipt_notes');
            $table->timestamp('reconciled_at')->nullable()->after('reconciliation_status');

            // Overage handling
            $table->boolean('overage_approved')->default(false)->after('reconciled_at');
            $table->decimal('overage_threshold', 12, 2)->nullable()->after('overage_approved');

            // Capture details
            $table->string('capture_idempotency_key')->nullable()->after('capture_reference');
            $table->timestamp('payment_captured_at')->nullable()->change();

            // Refund details
            $table->string('refund_idempotency_key')->nullable()->after('refund_reference');

            // Payout tracking
            $table->decimal('merchant_purchase_amount', 12, 2)->nullable()->after('driver_payout_amount');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('merchant_purchase_amount');
            $table->decimal('processing_reserve', 12, 2)->nullable()->after('tax_amount');
            $table->decimal('dispatcher_commission', 12, 2)->nullable()->after('processing_reserve');
            $table->decimal('urban_goodz_revenue', 12, 2)->nullable()->after('dispatcher_commission');

            // Financial rule snapshot (JSON: captures exact rules used for splits at quote time)
            $table->json('financial_rules_snapshot')->nullable()->after('urban_goodz_revenue');

            // Indexes
            $table->index('fulfillment_type');
            $table->index('sourcing_status');
            $table->index('shopper_id');
            $table->index('shopper_status');
            $table->index('reconciliation_status');
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_type', 'sourcing_status', 'shopper_id', 'shopper_status',
                'card_issued', 'card_request_id', 'authorization_expires_at',
                'receipt_amount', 'receipt_difference', 'receipt_image_path',
                'receipt_notes', 'reconciliation_status', 'reconciled_at',
                'overage_approved', 'overage_threshold',
                'capture_idempotency_key', 'refund_idempotency_key',
                'merchant_purchase_amount', 'tax_amount', 'processing_reserve',
                'dispatcher_commission', 'urban_goodz_revenue', 'financial_rules_snapshot',
            ]);
        });
    }
};
