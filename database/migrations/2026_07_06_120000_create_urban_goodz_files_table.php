<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_files')) {
            return;
        }

        Schema::create('urban_goodz_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('owner_type')->nullable()->index();
            $table->string('file_category')->index();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->json('metadata')->nullable();
            $table->string('visibility')->default('customer_private');
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_files');
    }
};
