<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('image', 255)->nullable();
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};