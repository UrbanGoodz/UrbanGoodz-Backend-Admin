<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'cash_back_id')) {
                $table->foreignId('cash_back_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'extra_packaging_amount')) {
                $table->double('extra_packaging_amount',23, 3)->default(0);
            }
            if (!Schema::hasColumn('orders', 'ref_bonus_amount')) {
                $table->double('ref_bonus_amount',23, 3)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cash_back_id');
            $table->dropColumn('extra_packaging_amount');
            $table->dropColumn('ref_bonus_amount');
        });
    }
};
