<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('f_name', 100)->nullable();
                $table->string('l_name', 100)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email', 100)->unique();
                $table->string('image', 255)->nullable();
                $table->string('password', 100);
                $table->string('remember_token', 100)->nullable();
                $table->string('login_remember_token', 255)->nullable();
                $table->unsignedBigInteger('role_id')->nullable()->index();
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->boolean('is_logged_in')->default(false);
                $table->string('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->boolean('two_factor_enabled')->default(false);
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};