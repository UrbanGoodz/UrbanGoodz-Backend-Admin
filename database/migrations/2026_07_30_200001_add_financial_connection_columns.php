<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_creator_earnings')) {
            Schema::table('urban_goodz_creator_earnings', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_creator_earnings', 'ledger_entry_id')) {
                    $table->unsignedBigInteger('ledger_entry_id')->nullable();
                }
                if (!Schema::hasColumn('urban_goodz_creator_earnings', 'settlement_snapshot')) {
                    $table->json('settlement_snapshot')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Add down logic if necessary
    }
};
