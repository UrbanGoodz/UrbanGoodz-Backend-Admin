<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('delivery_man_id')->nullable()->index();
                $table->string('title', 255);
                $table->text('data')->nullable();
                $table->string('type', 50)->nullable();
                $table->string('image', 255)->nullable();
                $table->boolean('is_read')->default(false);
                $table->string('order_id', 50)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};