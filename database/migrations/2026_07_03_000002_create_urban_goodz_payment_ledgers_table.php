<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_payment_ledgers')) {
            return;
        }

        Schema::create('urban_goodz_payment_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('ledger_number')->unique();
            $table->string('feature')->index();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('event_type')->index();
            $table->string('direction')->default('credit');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->index();
            $table->string('reference')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('delivery_man_id')->nullable()->index();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_payment_ledgers');
    }
};
