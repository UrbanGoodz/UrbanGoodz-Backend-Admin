<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_wallets')) {
            Schema::create('admin_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->decimal('balance', 24, 3)->default(0);
                $table->decimal('delivery_charge', 24, 3)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_wallets');
    }
};