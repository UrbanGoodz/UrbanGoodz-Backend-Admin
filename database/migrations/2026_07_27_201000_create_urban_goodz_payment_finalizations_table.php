<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_payment_finalizations')) {
            return;
        }

        Schema::create('urban_goodz_payment_finalizations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('payment_intent_id', 128)->index();
            $table->string('internal_reference', 191)->index();
            $table->string('operation', 32)->default('capture');
            $table->string('payable_type', 191);
            $table->unsignedBigInteger('payable_id');
            $table->string('canonical_key', 191)->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 32)->default('processing')->index();
            $table->unsignedBigInteger('capture_ledger_id')->nullable();
            $table->unsignedBigInteger('split_ledger_id')->nullable();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->unsignedInteger('ledger_count')->default(0);
            $table->unsignedInteger('split_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id', 'operation'], 'ug_payment_finalization_payable');
            $table->unique(
                ['provider', 'payment_intent_id', 'internal_reference', 'operation'],
                'ug_payment_finalization_business_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_payment_finalizations');
    }
};
