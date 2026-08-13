<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('f_name', 100)->nullable();
                $table->string('l_name', 100)->nullable();
                $table->string('phone', 255)->nullable()->unique();
                $table->string('email', 100)->nullable();
                $table->string('image', 100)->nullable();
                $table->boolean('is_phone_verified')->default(false);
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password', 100)->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->string('interest', 255)->nullable();
                $table->string('cm_firebase_token', 255)->nullable();
                $table->boolean('status')->default(true);
                $table->integer('order_count')->default(0);
                $table->string('login_medium', 255)->nullable();
                $table->string('social_id', 255)->nullable();
                $table->unsignedBigInteger('zone_id')->nullable()->index();
                $table->decimal('wallet_balance', 24, 3)->default(0);
                $table->decimal('loyalty_point', 24, 3)->default(0);
                $table->string('ref_code', 10)->nullable()->unique();
                $table->string('current_language_key', 255)->default('en');
                $table->unsignedBigInteger('ref_by')->nullable();
                $table->string('temp_token', 255)->nullable();
                $table->string('module_ids', 255)->nullable();
                $table->boolean('is_email_verified')->default(false);
                $table->boolean('is_from_pos')->default(false);
            });
        }

        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->string('f_name', 100);
                $table->string('l_name', 100)->nullable();
                $table->string('phone', 20);
                $table->string('email', 100);
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password', 100);
                $table->rememberToken();
                $table->timestamps();
                $table->text('rejection_note')->nullable();
                $table->string('branch', 255)->nullable();
                $table->string('holder_name', 255)->nullable();
                $table->string('account_no', 255)->nullable();
                $table->string('image', 255)->nullable();
                $table->boolean('status')->default(true);
                $table->string('firebase_token', 255)->nullable();
                $table->string('auth_token', 255)->nullable();
                $table->string('login_remember_token', 255)->nullable();
                $table->unique(['phone'], 'vendors_phone_unique');
                $table->unique(['email'], 'vendors_email_unique');
            });
        }
    }

    public function down(): void
    {
        // Defensive: never drop tables other migrations/seed data depend on.
    }
};
