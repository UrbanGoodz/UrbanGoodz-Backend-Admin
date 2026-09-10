<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records item substitutions on an Order Anywhere purchase.
 *
 * The platform could already PROPOSE substitutions - OrderAnywhereNLPService::
 * suggestSubstitutions() is called from UrbanGoodzAIExecutionService - but had
 * nowhere to record what was actually swapped. The only place item data lived
 * was the cart_items JSON blob on the request, and editing that in place
 * destroys the history: once "Brand A $10.00" becomes "Brand B $12.00" there is
 * no longer any evidence of what the customer originally asked for, what they
 * were quoted, who approved the change, or where the extra $2.00 came from.
 *
 * Each row is one swap, keeping both sides and the money delta, so a disputed
 * order can be explained line by line long after the fact.
 *
 * The financial rule this encodes: a substitution's price_delta is part of the
 * MERCHANDISE cost. It changes the purchase funds the customer owes, never the
 * driver's earnings and never Urban Goodz revenue - a driver must not profit
 * from swapping to a pricier brand, nor absorb the cost of a cheaper one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_order_anywhere_item_substitutions')) {
            return;
        }

        Schema::create('urban_goodz_order_anywhere_item_substitutions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_anywhere_request_id');
            // Present when the purchase was made on an issued virtual card.
            $table->unsignedBigInteger('card_request_id')->nullable();

            // Identifies which requested line this swap replaces. Kept as a
            // string because cart_items entries are not database rows.
            $table->string('line_reference', 191)->nullable();

            // ─── what the customer asked for ──────────────────────────────
            $table->string('original_item_name', 255);
            $table->decimal('original_quantity', 10, 2)->default(1);
            $table->decimal('original_estimated_price', 12, 2)->default(0);

            // ─── what was actually bought ─────────────────────────────────
            // Null while the substitution is only proposed, and on a removal
            // (item unavailable, nothing put in its place).
            $table->string('substituted_item_name', 255)->nullable();
            $table->decimal('substituted_quantity', 10, 2)->nullable();
            $table->decimal('substituted_actual_price', 12, 2)->nullable();

            // Positive = the swap cost MORE than the customer was quoted.
            $table->decimal('price_delta', 12, 2)->default(0);

            // unavailable | out_of_stock | driver_choice | customer_request | removed
            $table->string('reason', 40)->default('unavailable');

            // proposed | customer_approved | customer_rejected | auto_approved | applied | cancelled
            $table->string('status', 30)->default('proposed');

            // Swaps beyond the configured tolerance need the customer to agree
            // before the driver spends their money on something else.
            $table->boolean('requires_customer_approval')->default(false);

            $table->string('proposed_by', 30)->default('driver'); // driver | ai | admin | customer
            $table->unsignedBigInteger('proposed_by_id')->nullable();

            $table->timestamp('customer_responded_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->text('notes')->nullable();
            $table->json('safe_metadata')->nullable();

            $table->timestamps();

            $table->index('order_anywhere_request_id', 'oa_item_sub_request_idx');
            $table->index('card_request_id', 'oa_item_sub_card_idx');
            $table->index(['order_anywhere_request_id', 'status'], 'oa_item_sub_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_order_anywhere_item_substitutions');
    }
};
