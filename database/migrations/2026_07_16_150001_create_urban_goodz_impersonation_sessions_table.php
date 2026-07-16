<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_impersonation_sessions')) {
            return;
        }

        Schema::create('urban_goodz_impersonation_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('business_client_id');
            $table->string('mode')->default('read_only');
            $table->string('session_token')->unique()->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('exit_admin_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins');
            $table->foreign('business_client_id')->references('id')->on('urban_goodz_business_clients');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_impersonation_sessions');
    }
};
