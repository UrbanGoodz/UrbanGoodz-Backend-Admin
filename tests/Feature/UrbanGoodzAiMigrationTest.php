<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UrbanGoodzAiMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
    }

    public function test_migration_is_safe_and_handles_partial_state()
    {
        // Load the migration anonymous class
        $migrationPath = database_path('migrations/2026_07_19_160400_create_merchant_prospects_table.php');
        $this->assertFileExists($migrationPath);
        $migration = require $migrationPath;

        // Ensure the referenced table exists for foreign key constraints
        if (!Schema::hasTable('order_anywhere_requests')) {
            Schema::create('order_anywhere_requests', function ($table) {
                $table->id();
                $table->timestamps();
            });
        }

        // 1. Clean up first
        Schema::dropIfExists('merchant_prospect_order_anywhere');
        Schema::dropIfExists('merchant_prospects');

        // 2. Run fresh migration
        $migration->up();

        // 3. Verify tables exist
        $this->assertTrue(Schema::hasTable('merchant_prospects'));
        $this->assertTrue(Schema::hasTable('merchant_prospect_order_anywhere'));

        // Check index/constraint names length (<= 64 characters)
        $foreignKeys = Schema::getForeignKeys('merchant_prospect_order_anywhere');
        $this->assertNotEmpty($foreignKeys);
        
        foreach ($foreignKeys as $fk) {
            $name = $fk['name'];
            if (!empty($name)) {
                $this->assertTrue(
                    strlen($name) <= 64, 
                    "Foreign key name '$name' is too long (" . strlen($name) . " chars)"
                );
                $this->assertTrue(
                    in_array($name, ['mp_oa_prospect_fk', 'mp_oa_request_fk']),
                    "Unexpected foreign key name '$name'"
                );
            }
        }

        // 4. Insert dummy data to simulate existing business records
        DB::table('merchant_prospects')->insert([
            'id' => 1,
            'business_name' => 'Existing Business',
            'business_name_normalized' => 'existing business',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify record exists
        $this->assertEquals(1, DB::table('merchant_prospects')->count());

        // 5. Simulate a partial migration failure:
        // Junction table exists but let's drop it so the migration has to recreate it
        Schema::dropIfExists('merchant_prospect_order_anywhere');

        // 6. Rerun the migration
        $migration->up();

        // 7. Verify the junction table is recreated successfully
        $this->assertTrue(Schema::hasTable('merchant_prospect_order_anywhere'));

        // 8. Verify the existing records in merchant_prospects are NOT deleted
        $this->assertEquals(1, DB::table('merchant_prospects')->count());
        $this->assertEquals('Existing Business', DB::table('merchant_prospects')->first()->business_name);

    }
}
