<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('urban_goodz_ai_conversations', 'session_id')) {
            Schema::table('urban_goodz_ai_conversations', function (Blueprint $table) {
                $table->string('session_id', 64)->nullable()->index()->after('customer_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('urban_goodz_ai_conversations', 'session_id')) {
            Schema::table('urban_goodz_ai_conversations', function (Blueprint $table) {
                $table->dropColumn('session_id');
            });
        }
    }
};
