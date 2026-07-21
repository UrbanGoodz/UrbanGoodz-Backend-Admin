<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_anywhere_requests', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->index()->after('customer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_anywhere_requests', function (Blueprint $table) {
            if (Schema::hasColumn('order_anywhere_requests', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });
    }
};
