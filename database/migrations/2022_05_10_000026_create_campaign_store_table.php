<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_store')) {
            Schema::create('campaign_store', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id')->index();
                $table->unsignedBigInteger('store_id')->index();
                $table->string('campaign_status', 50)->default('active');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_store');
    }
};