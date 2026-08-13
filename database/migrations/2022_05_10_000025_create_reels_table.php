<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reels')) {
            Schema::create('reels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('creator_id')->nullable()->index();
                $table->string('title', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('video', 255)->nullable();
                $table->string('thumbnail', 255)->nullable();
                $table->integer('views')->default(0);
                $table->integer('likes')->default(0);
                $table->integer('status')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reels');
    }
};