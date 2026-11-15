<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OpsSafetyNetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_error_tracking_is_installed_but_completely_off_without_a_dsn(): void
    {
        $this->assertTrue(class_exists(\Sentry\Laravel\Integration::class), 'the Sentry package must be installed');
        $this->assertEmpty(config('sentry.dsn'), 'no DSN means nothing leaves the server');
        $this->assertFalse(config('sentry.send_default_pii'), 'no cookies, IPs or user details');
        $this->assertFalse(config('sentry.breadcrumbs.sql_bindings'));
        $this->assertSame(0.0, (float) config('sentry.traces_sample_rate'));
    }

    public function test_an_unexpected_error_still_renders_normally_with_tracking_installed(): void
    {
        \Illuminate\Support\Facades\Route::get('/__boom', fn () => throw new \RuntimeException('boom'));

        $this->getJson('/__boom')->assertStatus(500);
    }

    public function test_the_staging_scrubber_refuses_to_run_outside_staging(): void
    {
        $before = DB::table('users')->count();

        $code = Artisan::call('staging:sanitize', ['--force' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Refusing to run', Artisan::output());
        $this->assertSame($before, DB::table('users')->count());
    }

    public function test_the_staging_scrubber_also_refuses_a_non_staging_database_even_in_the_staging_env(): void
    {
        $this->app['env'] = 'staging';

        $code = Artisan::call('staging:sanitize', ['--force' => true]);

        $this->assertSame(1, $code, 'the database name must contain "staging" too');
    }

    public function test_the_backup_script_is_valid_shell_and_never_prints_credentials(): void
    {
        $script = file_get_contents(base_path('scripts/ops/backup.sh'));
        exec('bash -n '.escapeshellarg(base_path('scripts/ops/backup.sh')).' 2>&1', $out, $rc);
        $this->assertSame(0, $rc, implode("\n", $out));
        $this->assertStringContainsString('--defaults-extra-file', $script, 'credentials go through a 0600 file, not the command line');
        $this->assertStringNotContainsString('-p"$DB_PASS"', $script);
        $this->assertStringNotContainsString('echo "$DB_PASS', $script);
    }

    public function test_the_sanitizer_scrubs_every_real_value_and_proves_it(): void
    {
        $u = \App\Models\User::create(['f_name' => 'Real', 'l_name' => 'Person', 'email' => 'real.person@gmail.com', 'phone' => '+13125550101', 'password' => bcrypt('x')]);
        DB::table('users')->where('id', $u->id)->update(['cm_firebase_token' => 'real-device-token']);
        $d = \App\Models\DeliveryMan::create(['f_name' => 'Real', 'l_name' => 'Driver', 'phone' => '+13125550102', 'email' => 'real.driver@gmail.com', 'password' => bcrypt('x'), 'auth_token' => 'tok-real']);
        foreach (Schema::getColumnListing('delivery_men') as $col) {
            if (stripos($col, 'token') !== false) {
                DB::table('delivery_men')->where('id', $d->id)->update([$col => 'real-'.$col]);
            }
        }

        $result = app(\App\Services\StagingSanitizer::class)->run();

        $this->assertTrue($result['ok'], implode('; ', $result['problems']));
        $this->assertSame("user{$u->id}@staging.invalid", DB::table('users')->where('id', $u->id)->value('email'));
        $this->assertSame("driver{$d->id}@staging.invalid", DB::table('delivery_men')->where('id', $d->id)->value('email'));
        $this->assertNull(DB::table('users')->where('id', $u->id)->value('cm_firebase_token'));
        foreach (Schema::getColumnListing('delivery_men') as $col) {
            if (stripos($col, 'token') !== false) {
                $this->assertNull(DB::table('delivery_men')->where('id', $d->id)->value($col), "{$col} must be wiped, whatever this environment calls it");
            }
        }
        $this->assertSame([], app(\App\Services\StagingSanitizer::class)->verify());
    }

    public function test_the_verification_pass_fails_loudly_when_real_data_remains(): void
    {
        app(\App\Services\StagingSanitizer::class)->run();
        \App\Models\User::create(['f_name' => 'Late', 'l_name' => 'Arrival', 'email' => 'late@gmail.com', 'phone' => '+13125550103', 'password' => bcrypt('x')]);

        $problems = app(\App\Services\StagingSanitizer::class)->verify();

        $this->assertNotEmpty($problems, 'a leftover real email must be reported, never silently accepted');
        $this->assertStringContainsString('real email', implode(' ', $problems));
    }
}
