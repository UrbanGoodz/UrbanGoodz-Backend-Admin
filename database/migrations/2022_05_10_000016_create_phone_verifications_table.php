<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('phone_verifications')) {
            Schema::create('phone_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('phone', 20);
                $table->string('code', 10);
                $table->integer('otp_hit_count')->default(0);
                $table->timestamp('hit_count_at')->nullable();
                $table->timestamp('temp_block_time')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');
    }
};