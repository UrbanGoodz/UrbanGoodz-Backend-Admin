<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendor_employees')) {
            Schema::create('vendor_employees', function (Blueprint $table) {
                $table->id();
                $table->string('f_name', 100)->nullable();
                $table->string('l_name', 100)->nullable();
                $table->string('phone', 20);
                $table->string('email', 100);
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password', 100);
                $table->rememberToken();
                $table->timestamps();
                $table->text('rejection_note')->nullable();
                $table->string('image', 255)->nullable();
                $table->boolean('status')->default(true);
                $table->string('firebase_token', 255)->nullable();
                $table->string('auth_token', 255)->nullable();
                $table->string('login_remember_token', 255)->nullable();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->unique(['phone'], 'vendor_employees_phone_unique');
                $table->unique(['email'], 'vendor_employees_email_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_employees');
    }
};