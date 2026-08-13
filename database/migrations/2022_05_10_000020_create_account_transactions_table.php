<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_transactions')) {
            Schema::create('account_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('delivery_man_id')->nullable()->index();
                $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->decimal('amount', 24, 3)->default(0);
                $table->string('type', 50)->nullable();
                $table->string('status', 50)->default('pending');
                $table->text('details')->nullable();
                $table->string('reference', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};