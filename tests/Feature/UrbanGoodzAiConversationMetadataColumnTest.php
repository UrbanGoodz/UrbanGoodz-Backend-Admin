<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzAIConversation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UrbanGoodzAiConversationMetadataColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::dropIfExists('urban_goodz_ai_conversations');
        Schema::dropIfExists('urban_goodz_ai_intents');

        Schema::create('urban_goodz_ai_intents', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $create = require database_path('migrations/2026_07_06_140007_create_urban_goodz_ai_conversations_table.php');
        $create->up();
    }

    public function test_original_migration_has_no_metadata_column()
    {
        $this->assertFalse(Schema::hasColumn('urban_goodz_ai_conversations', 'metadata'));
    }

    public function test_conversation_creation_fails_without_the_fix()
    {
        // Reproduces the production bug: the model always populates 'metadata',
        // but the original table never had the column.
        $this->expectException(\Illuminate\Database\QueryException::class);

        UrbanGoodzAIConversation::create([
            'query_text' => 'What is Order Anywhere?',
            'status' => 'resolved',
            'source' => 'admin_test',
            'metadata' => ['response_source' => 'ai_provider'],
        ]);
    }

    public function test_add_metadata_migration_fixes_creation_and_round_trips_as_array()
    {
        $addColumn = require database_path('migrations/2026_07_28_200000_add_metadata_to_urban_goodz_ai_conversations.php');
        $addColumn->up();

        $this->assertTrue(Schema::hasColumn('urban_goodz_ai_conversations', 'metadata'));

        $conversation = UrbanGoodzAIConversation::create([
            'query_text' => 'What is Order Anywhere?',
            'status' => 'resolved',
            'source' => 'admin_test',
            'metadata' => [
                'response_source' => 'ai_provider',
                'provider_success' => true,
                'provider_error_code' => null,
            ],
        ]);

        $fresh = UrbanGoodzAIConversation::findOrFail($conversation->id);
        $this->assertIsArray($fresh->metadata);
        $this->assertSame('ai_provider', $fresh->metadata['response_source']);
        $this->assertTrue($fresh->metadata['provider_success']);
    }

    public function test_add_metadata_migration_is_idempotent()
    {
        $addColumn = require database_path('migrations/2026_07_28_200000_add_metadata_to_urban_goodz_ai_conversations.php');
        $addColumn->up();
        $addColumn->up(); // second run must not throw "duplicate column"

        $this->assertTrue(Schema::hasColumn('urban_goodz_ai_conversations', 'metadata'));
    }

    public function test_down_migration_drops_the_column()
    {
        $addColumn = require database_path('migrations/2026_07_28_200000_add_metadata_to_urban_goodz_ai_conversations.php');
        $addColumn->up();
        $addColumn->down();

        $this->assertFalse(Schema::hasColumn('urban_goodz_ai_conversations', 'metadata'));
    }
}
