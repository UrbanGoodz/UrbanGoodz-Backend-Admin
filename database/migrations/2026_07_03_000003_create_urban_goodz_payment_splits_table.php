<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_payment_splits')) {
            return;
        }

        Schema::create('urban_goodz_payment_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ledger_id')->index();
            $table->string('feature')->index();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('recipient_type')->index();
            $table->unsignedBigInteger('recipient_id')->nullable()->index();
            $table->string('split_type')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('status')->default('pending')->index();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_payment_splits');
    }
};
