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
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'tin')) {
                $table->string('tin')->nullable();
            }
            if (!Schema::hasColumn('stores', 'tin_expire_date')) {
                $table->date('tin_expire_date')->nullable();
            }
            if (!Schema::hasColumn('stores', 'tin_certificate_image')) {
                $table->string('tin_certificate_image')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('tin');
            $table->dropColumn('tin_expire_date');
            $table->dropColumn('tin_certificate_image');
        });
    }
};
