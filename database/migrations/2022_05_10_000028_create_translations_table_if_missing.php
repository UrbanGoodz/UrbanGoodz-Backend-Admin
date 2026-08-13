<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * App\Models\Translation (public $timestamps = false) backs the
     * ->translations() morphMany relation used by 40+ models (Item, Store,
     * Category, Module, Banner, etc.) for multi-language field storage. No
     * migration ever created this table -- it was missing from the
     * from-scratch schema entirely, so touching any translatable model's
     * ->translations relation (e.g. loading a Module row, which happens on
     * most authenticated admin/API requests) threw "Base table or view not
     * found: translations".
     */
    public function up(): void
    {
        if (!Schema::hasTable('translations')) {
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->string('translationable_type');
                $table->unsignedBigInteger('translationable_id');
                $table->string('locale', 5)->nullable();
                $table->string('key')->nullable();
                $table->text('value')->nullable();

                $table->index(['translationable_type', 'translationable_id'], 'translations_translationable_index');
                $table->index('locale');
                $table->index('key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
