<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verifications', function (Blueprint $table) {
            if (!Schema::hasColumn('email_verifications', 'otp_hit_count')) {
                $table->tinyInteger('otp_hit_count')->default(0);
            }
            if (!Schema::hasColumn('email_verifications', 'is_temp_blocked')) {
                $table->boolean('is_temp_blocked')->default(0);
            }
            if (!Schema::hasColumn('email_verifications', 'temp_block_time')) {
                $table->timestamp('temp_block_time')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_verifications', function (Blueprint $table) {
            $table->dropColumn(['otp_hit_count', 'is_temp_blocked', 'temp_block_time']);
        });
    }
};
