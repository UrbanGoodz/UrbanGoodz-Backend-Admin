<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urban_goodz_book_anywhere_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('service_name');
            $table->text('description')->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time')->nullable();
            $table->string('location')->nullable();
            $table->decimal('budget_amount', 10, 2)->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('assigned_provider_id')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_book_anywhere_requests');
    }
};
