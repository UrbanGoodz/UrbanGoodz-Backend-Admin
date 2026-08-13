<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('banners')) {
            Schema::create('banners', function (Blueprint $table) {
                $table->id();
                $table->string('title', 255)->nullable();
                $table->string('resource_type', 50)->nullable();
                $table->string('resource_id', 50)->nullable();
                $table->string('image', 255)->nullable();
                $table->string('link', 500)->nullable();
                $table->integer('position')->default(0);
                $table->boolean('status')->default(true);
                $table->string('default_link', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};