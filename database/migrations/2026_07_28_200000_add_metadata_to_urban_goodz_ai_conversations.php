<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The model has cast 'metadata' => 'array' and fillable 'metadata' since the
 * AI concierge service was written to always populate it, but the original
 * create migration never added the column — every insert has been failing
 * with "Unknown column 'metadata'" since that service shipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('urban_goodz_ai_conversations', 'metadata')) {
            return;
        }

        Schema::table('urban_goodz_ai_conversations', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('urban_goodz_ai_conversations', 'metadata')) {
            return;
        }

        Schema::table('urban_goodz_ai_conversations', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
