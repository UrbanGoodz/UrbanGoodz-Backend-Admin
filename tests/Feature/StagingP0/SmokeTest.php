<?php

namespace Tests\Feature\StagingP0;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\StagingP0\Concerns\CreatesP0Fixtures;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesP0Fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureP0Fixtures();
    }

    public function test_staging_db_and_fixtures_present(): void
    {
        // Superseded by tests/CreatesApplication.php's allowlist guard,
        // which now enforces "never a prod/staging/demo-named database" for
        // every test in the suite, not just this one - so the safety this
        // test existed for no longer depends on one hardcoded database name.
        $database = (string) DB::connection()->getConfig('database');
        $allowlist = array_filter(array_map(
            'trim',
            explode(',', (string) ($_SERVER['UG_TEST_DB_ALLOWLIST'] ?? getenv('UG_TEST_DB_ALLOWLIST') ?: ''))
        ));

        $this->assertContains($database, $allowlist, 'Connected database is not on the explicit test-DB allowlist.');
        $this->assertSame(3, DB::table('delivery_men')->whereIn('id', [9001, 9002, 9003])->count());
    }
}
