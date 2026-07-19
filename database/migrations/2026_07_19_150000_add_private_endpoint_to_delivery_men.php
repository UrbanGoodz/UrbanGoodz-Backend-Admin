<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->string('private_endpoint_address')->nullable();
            $table->decimal('private_endpoint_lat', 10, 7)->nullable();
            $table->decimal('private_endpoint_lng', 10, 7)->nullable();
            $table->string('private_endpoint_status')->default('pending');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->dropColumn([
                'private_endpoint_address',
                'private_endpoint_lat',
                'private_endpoint_lng',
                'private_endpoint_status',
            ]);
        });
    }
};
