<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                // Deliberately no unique index on `key` -- App\Models\BusinessSetting
                // callers use updateOrCreate() rather than an upsert (see
                // 2026_08_06_000003_make_stranded_pricing_configurable.php), and a
                // unique constraint here was never part of the live schema this
                // reconstructs from.
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
