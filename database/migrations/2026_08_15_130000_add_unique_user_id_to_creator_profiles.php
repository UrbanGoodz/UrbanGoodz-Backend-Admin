<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CreatorSpaceController::register() had no check for an existing profile,
 * so calling it twice created two urban_goodz_creator_profiles rows for the
 * same user_id. The controller now blocks the duplicate at the application
 * level; this closes the same gap under concurrent requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_creator_profiles')
            && Schema::hasColumn('urban_goodz_creator_profiles', 'user_id')
            && ! Schema::hasIndex('urban_goodz_creator_profiles', 'ug_creator_profiles_user_unique')) {
            Schema::table('urban_goodz_creator_profiles', function (Blueprint $table) {
                $table->unique('user_id', 'ug_creator_profiles_user_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_creator_profiles')
            && Schema::hasIndex('urban_goodz_creator_profiles', 'ug_creator_profiles_user_unique')) {
            Schema::table('urban_goodz_creator_profiles', function (Blueprint $table) {
                $table->dropUnique('ug_creator_profiles_user_unique');
            });
        }
    }
};
