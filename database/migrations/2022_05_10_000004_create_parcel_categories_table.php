<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parcel_categories')) {
            Schema::create('parcel_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('image', 255)->nullable();
                $table->text('description')->nullable();
                $table->integer('position')->default(0);
                $table->boolean('status')->default(true);
                $table->boolean('delivery')->default(true);
                $table->decimal('min_price', 24, 3)->default(0);
                $table->decimal('price_per_km', 24, 3)->default(0);
                $table->decimal('price_per_kg', 24, 3)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_categories');
    }
};