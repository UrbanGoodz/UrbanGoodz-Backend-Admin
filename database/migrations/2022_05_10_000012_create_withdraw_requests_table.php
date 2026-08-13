<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('withdraw_requests')) {
            Schema::create('withdraw_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('delivery_man_id')->nullable()->index();
                $table->decimal('amount', 24, 3)->default(0);
                $table->string('status', 50)->default('pending');
                $table->string('method', 50)->nullable();
                $table->text('details')->nullable();
                $table->string('created_by', 50)->nullable();
                $table->string('sender_note', 500)->nullable();
                $table->string('user_note', 500)->nullable();
                $table->string('rejection_note', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_requests');
    }
};