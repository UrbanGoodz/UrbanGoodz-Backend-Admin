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
            if (!Schema::hasColumn('orders', 'flash_admin_discount_amount')) {
                $table->double('flash_admin_discount_amount', 24, 3)->default(0);
            }
            if (!Schema::hasColumn('orders', 'flash_store_discount_amount')) {
                $table->double('flash_store_discount_amount', 24, 3)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('flash_admin_discount_amount');
            $table->dropColumn('flash_store_discount_amount');
        });
    }
};
