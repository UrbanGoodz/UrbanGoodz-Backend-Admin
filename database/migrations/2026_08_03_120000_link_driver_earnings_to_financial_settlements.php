<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urban_goodz_driver_earnings')
            || Schema::hasColumn('urban_goodz_driver_earnings', 'financial_settlement_snapshot_id')) {
            return;
        }

        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            $table->unsignedBigInteger('financial_settlement_snapshot_id')
                ->nullable()
                ->after('settlement_snapshot_id');
            $table->unique(
                'financial_settlement_snapshot_id',
                'ug_de_fin_settlement_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('urban_goodz_driver_earnings')
            || ! Schema::hasColumn('urban_goodz_driver_earnings', 'financial_settlement_snapshot_id')) {
            return;
        }

        Schema::table('urban_goodz_driver_earnings', function (Blueprint $table) {
            $table->dropUnique('ug_de_fin_settlement_unique');
            $table->dropColumn('financial_settlement_snapshot_id');
        });
    }
};
