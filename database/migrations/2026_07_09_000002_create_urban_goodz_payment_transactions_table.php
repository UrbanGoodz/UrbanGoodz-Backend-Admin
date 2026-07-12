<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('provider')->index();
            $table->string('environment')->nullable()->index();
            $table->string('transaction_type')->index();
            $table->string('internal_status')->index();
            $table->string('provider_status')->nullable();
            $table->decimal('amount_minor', 12, 0);
            $table->string('currency', 8)->default('USD');
            $table->string('merchant_reference')->nullable()->index();
            $table->string('provider_reference')->nullable()->index();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_payment_link_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('parent_transaction_id')->nullable();
            $table->string('request_payload_hash', 64)->nullable();
            $table->json('response_summary')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['provider', 'provider_reference'], 'ug_pay_tx_provider_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_payment_transactions');
    }
};
