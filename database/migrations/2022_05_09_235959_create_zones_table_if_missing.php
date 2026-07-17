<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('zones')) {
            return;
        }

        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->longText('coordinates')->nullable();
            $table->string('deliveryman_wise_topic')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op: this table is a legacy baseline dependency.
    }
};
