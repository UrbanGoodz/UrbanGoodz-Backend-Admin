<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('contact_person_name');
            $table->string('contact_person_number');
            $table->string('address_type');
            $table->text('address');
            $table->string('floor')->nullable();
            $table->string('road')->nullable();
            $table->string('house')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};