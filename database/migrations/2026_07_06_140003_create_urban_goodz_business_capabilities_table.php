<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_business_capabilities')) {
            return;
        }

        Schema::create('urban_goodz_business_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capability_id')->constrained('urban_goodz_capabilities')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'capability_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_business_capabilities');
    }
};
