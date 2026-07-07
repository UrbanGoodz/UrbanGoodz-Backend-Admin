<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_capabilities')) {
            return;
        }

        Schema::create('urban_goodz_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('admin_section_key')->nullable()->index();
            $table->string('group')->nullable()->index();
            $table->boolean('is_core')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_capabilities');
    }
};
