<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_waitlist')) {
            return;
        }

        Schema::create('urban_goodz_waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 191);
            $table->string('email', 191);
            $table->string('phone', 40)->nullable();
            $table->string('city', 191)->nullable();
            $table->string('interest', 50)->default('other');
            $table->text('message')->nullable();
            $table->string('source', 191)->nullable();
            $table->string('page', 191)->nullable();
            $table->boolean('consent')->default(false);
            $table->string('user_agent', 191)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 20)->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
            $table->index(['interest', 'status']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_waitlist')) {
            Schema::dropIfExists('urban_goodz_waitlist');
        }
    }
};
