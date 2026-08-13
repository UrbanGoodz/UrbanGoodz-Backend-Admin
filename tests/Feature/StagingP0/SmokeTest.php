<?php
namespace Tests\Feature\StagingP0;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
class SmokeTest extends TestCase {
    public function test_staging_db_and_fixtures_present(): void {
        $this->assertSame('urbangoodz_isolated_staging_20260723', DB::connection()->getDatabaseName());
        $this->assertSame(3, DB::table('delivery_men')->whereIn('id',[9001,9002,9003])->count());
    }
}
