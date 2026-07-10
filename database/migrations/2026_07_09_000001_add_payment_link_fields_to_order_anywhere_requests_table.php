<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $table->string('psp_reference')->nullable()->after('refund_reference');
            $table->string('merchant_reference')->nullable()->after('psp_reference');
            $table->string('payment_link_id')->nullable()->after('merchant_reference');
            $table->text('payment_url')->nullable()->after('payment_link_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $table->dropColumn(['psp_reference', 'merchant_reference', 'payment_link_id', 'payment_url']);
        });
    }
};
