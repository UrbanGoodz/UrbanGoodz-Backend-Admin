<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * database/seeders/UrbanGoodzLoadSourcingPermissionSeeder.php writes to
 * `permission_modules` (slug, label, module) via updateOrInsert(), but no
 * migration anywhere in the repository ever created this table — running
 * `php artisan db:seed` on a fresh install fails outright with
 * "Base table or view not found: permission_modules". No application code
 * reads from this table yet (the seeder is ahead of a permissions-catalog
 * UI that hasn't been built), but the table must exist for the seeder,
 * which is real committed code, to run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permission_modules')) {
            Schema::create('permission_modules', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('label');
                $table->string('module');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_modules');
    }
};
