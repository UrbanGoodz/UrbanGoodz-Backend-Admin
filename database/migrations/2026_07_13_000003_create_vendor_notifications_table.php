<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_notifications')) {
            return;
        }

        Schema::create('vendor_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('type')->default('info');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_notifications');
    }
};
