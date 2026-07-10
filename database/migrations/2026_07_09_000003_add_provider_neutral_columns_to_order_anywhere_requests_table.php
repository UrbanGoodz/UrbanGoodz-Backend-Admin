<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('order_anywhere_requests', 'payment_provider')) {
                $table->string('payment_provider')->nullable()->after('payment_status')->index();
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('payment_provider')->index();
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'provider_payment_id')) {
                $table->string('provider_payment_id')->nullable()->after('provider_reference');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'provider_payment_link_id')) {
                $table->string('provider_payment_link_id')->nullable()->after('provider_payment_id');
            }
            if (! Schema::hasColumn('order_anywhere_requests', 'provider_payment_url')) {
                $table->text('provider_payment_url')->nullable()->after('provider_payment_link_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            $table->dropColumn([
                'payment_provider',
                'provider_reference',
                'provider_payment_id',
                'provider_payment_link_id',
                'provider_payment_url',
            ]);
        });
    }
};
