<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NOTE: This migration originally created a standalone 'reels' table
     * (creator_id/title/views/likes shape) as part of a base-schema backfill.
     * That schema is not used anywhere in the codebase. The authoritative
     * 'reels' table is created by
     * Modules/ReelsModule/Database/Migrations/2026_04_07_160000_create_reels_table.php
     * (store_id/module_id/total_store_visits shape), which is what
     * App\Models\CreatorReelReport and App\Models\CreatorReelTag actually
     * relate to via Modules\ReelsModule\Entities\Reel. Creating the table
     * here first (this migration sorts earlier) would leave the wrong
     * schema in place and break those relations. Left as a deliberate
     * no-op so ReelsModule's migration remains the single source of truth.
     */
    public function up(): void
    {
        // Intentionally left blank -- see note above.
    }

    public function down(): void
    {
        // Intentionally left blank -- this migration no longer owns the table.
    }
};