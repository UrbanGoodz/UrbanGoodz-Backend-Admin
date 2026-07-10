<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'customer_age_confirmed_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('customer_age_confirmed_at')->nullable()->after('age_verification_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'customer_age_confirmed_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('customer_age_confirmed_at');
            });
        }
    }
};
