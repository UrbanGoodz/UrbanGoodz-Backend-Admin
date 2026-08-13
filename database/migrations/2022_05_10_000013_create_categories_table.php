<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->unsignedBigInteger('module_id')->nullable()->index();
                $table->integer('position')->default(0);
                $table->integer('priority')->default(0);
                $table->integer('status')->default(1);
                $table->integer('featured')->default(0);
                $table->integer('products_count')->default(0);
                $table->integer('childes_count')->default(0);
                $table->string('image', 255)->nullable();
                $table->string('slug', 255)->nullable()->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};