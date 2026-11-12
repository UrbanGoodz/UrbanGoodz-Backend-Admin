<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_order_anywhere_reimbursements')) {
            return;
        }
        Schema::create('urban_goodz_order_anywhere_reimbursements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_anywhere_request_id');
            $table->unsignedBigInteger('delivery_man_id');
            $table->decimal('claimed_total', 10, 2);
            $table->decimal('authorized_total', 10, 2);
            $table->decimal('cap_total', 10, 2);
            $table->decimal('approved_total', 10, 2)->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->string('receipt_path')->nullable();
            $table->string('receipt_mime', 100)->nullable();
            $table->text('driver_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('driver_earning_id')->nullable();
            $table->timestamps();

            // One live claim per order; a rejected claim can be resubmitted.
            $table->index(['order_anywhere_request_id', 'status'], 'ug_oa_reimb_req_status_idx');
            $table->index(['delivery_man_id', 'status'], 'ug_oa_reimb_driver_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_order_anywhere_reimbursements');
    }
};
