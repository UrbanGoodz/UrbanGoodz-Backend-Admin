<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Without this row, Monique has no way to recognize a customer describing a
 * roadside emergency -- she falls back to a generic answer instead of handing
 * off into the real Stranded / Goodz Samaritan workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('urban_goodz_ai_intents')->updateOrInsert(
            ['slug' => 'stranded'],
            [
                'name' => 'Stranded / Roadside Help',
                'description' => 'Customer describes being stranded, broken down, stuck, unsafe, or needing roadside help',
                'keywords' => json_encode([
                    'stranded', 'broke down', 'broken down', 'stuck', 'flat tire',
                    'dead battery', 'jump start', 'won\'t start', 'roadside',
                    'need a ride home', 'tow', 'accident', 'car trouble',
                    'stuck on the highway', 'stuck on the side of the road', 'help on the road',
                ], JSON_THROW_ON_ERROR),
                'response_template' => "Okay, let's get you some help. First, are you somewhere safe? I'm connecting you to Urban Goodz Stranded so a nearby Goodz Samaritan or professional can reach you.",
                'capability_slug' => 'stranded',
                'admin_section_key' => 'stranded',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('urban_goodz_ai_intents')->where('slug', 'stranded')->delete();
    }
};
