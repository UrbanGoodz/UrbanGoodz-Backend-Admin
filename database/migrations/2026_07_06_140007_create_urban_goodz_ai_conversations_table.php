<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_ai_conversations')) {
            return;
        }

        Schema::create('urban_goodz_ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->text('query_text');
            $table->foreignId('detected_intent_id')->nullable()->constrained('urban_goodz_ai_intents')->nullOnDelete();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('response_text')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('source')->default('customer_api')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_ai_conversations');
    }
};
