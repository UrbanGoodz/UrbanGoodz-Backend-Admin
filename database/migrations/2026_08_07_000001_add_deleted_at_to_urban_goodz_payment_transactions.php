<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives urban_goodz_payment_transactions the deleted_at column its model has
 * always assumed.
 *
 * UrbanGoodzPaymentTransaction declares `use SoftDeletes`, so every query
 * Eloquent builds against it appends `where deleted_at is null` -- against a
 * column that does not exist. Any read through the model therefore threw
 * SQLSTATE[42S22], which is why the table sat at zero rows: nothing had ever
 * successfully queried it.
 *
 * The column is added rather than the trait removed. A financial ledger should
 * be soft-deletable: rows in it are evidence, and hard-deleting a payment
 * record is not something this system should be able to do casually.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_payment_transactions')
            && !Schema::hasColumn('urban_goodz_payment_transactions', 'deleted_at')) {
            Schema::table('urban_goodz_payment_transactions', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_payment_transactions')
            && Schema::hasColumn('urban_goodz_payment_transactions', 'deleted_at')) {
            Schema::table('urban_goodz_payment_transactions', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
