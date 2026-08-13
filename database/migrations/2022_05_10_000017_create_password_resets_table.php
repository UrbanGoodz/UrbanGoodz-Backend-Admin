<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->id();
                $table->string('email', 100)->index();
                $table->string('token', 100);
                $table->string('phone', 20)->nullable()->index();
                $table->integer('hit_count')->default(0);
                $table->timestamp('hit_count_at')->nullable();
                $table->timestamp('temp_block_time')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};