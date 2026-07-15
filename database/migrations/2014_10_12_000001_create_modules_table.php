<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_name')->unique();
            $table->string('module_type');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('theme_id')->default(1);
            $table->tinyInteger('status')->default(1);
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};