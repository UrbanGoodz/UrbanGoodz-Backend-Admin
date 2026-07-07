<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_business_type_default_capabilities')) {
            return;
        }

        Schema::create('urban_goodz_business_type_default_capabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_type_id');
            $table->unsignedBigInteger('capability_id');
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['business_type_id', 'capability_id'], 'ugz_bt_pivot_unique');

            $table->foreign('business_type_id', 'ugz_bt_pivot_bus_fk')
                ->references('id')->on('urban_goodz_business_types')->cascadeOnDelete();
            $table->foreign('capability_id', 'ugz_bt_pivot_cap_fk')
                ->references('id')->on('urban_goodz_capabilities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_business_type_default_capabilities');
    }
};
