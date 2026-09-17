<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS opt-in did not exist anywhere on the user record. Monique's Stranded
 * notifications may send SMS, but only to a user who asked for it -- this is
 * that flag. Defaults to false: opt-in, not opt-out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sms_notifications_enabled')) {
                $table->boolean('sms_notifications_enabled')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sms_notifications_enabled')) {
                $table->dropColumn('sms_notifications_enabled');
            }
        });
    }
};
