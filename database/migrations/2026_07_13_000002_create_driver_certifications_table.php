<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_man_id')->constrained('delivery_men')->cascadeOnDelete();
            $table->string('name');
            $table->string('issuing_authority')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('active');
            $table->string('document_url')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('renewal_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_certifications');
    }
};
