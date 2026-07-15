<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_earn_money_applications')) {
            return;
        }

        Schema::table('urban_goodz_earn_money_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('urban_goodz_earn_money_applications', 'delivery_man_id')) {
                $table->foreignId('delivery_man_id')->nullable()->constrained('delivery_men')->nullOnDelete();
            }
            if (!Schema::hasColumn('urban_goodz_earn_money_applications', 'applied_at')) {
                $table->dateTime('applied_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('urban_goodz_earn_money_applications')) {
            return;
        }

        Schema::table('urban_goodz_earn_money_applications', function (Blueprint $table) {
            if (Schema::hasColumn('urban_goodz_earn_money_applications', 'delivery_man_id')) {
                $table->dropForeign(['delivery_man_id']);
                $table->dropColumn('delivery_man_id');
            }
            if (Schema::hasColumn('urban_goodz_earn_money_applications', 'applied_at')) {
                $table->dropColumn('applied_at');
            }
        });
    }
};
