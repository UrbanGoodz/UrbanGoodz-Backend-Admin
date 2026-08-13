<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('vendors', function (Blueprint $table) {
                $table->text('rejection_note')->nullable();
            });
        } else {
            if (Schema::hasColumn('vendors', 'bank_name') && !Schema::hasColumn('vendors', 'rejection_note')) {
                Schema::table('vendors', function (Blueprint $table) {
                     $table->renameColumn('bank_name', 'rejection_note');
                });
            }
            if (Schema::hasColumn('vendors', 'rejection_note')) {
                Schema::table('vendors', function (Blueprint $table) {
                    $table->text('rejection_note')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('rejection_note');
            });
        } else {
             Schema::table('vendors', function (Blueprint $table) {
                $table->string('rejection_note', 255)->change();
                $table->renameColumn('rejection_note', 'bank_name');
            });
        }
    }
};
