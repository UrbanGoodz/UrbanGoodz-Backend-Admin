<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Scrubs a COPY of production so it can be used as a staging database: real names, emails, phones, addresses,
 * device tokens, login tokens and conversation text are replaced or removed.
 *
 * It refuses to run unless the app is in the "staging" environment AND the database name contains "staging",
 * so it can never be pointed at production by accident.
 */
class SanitizeForStaging extends Command
{
    protected $signature = 'staging:sanitize {--force : Skip the confirmation prompt}';

    protected $description = 'Anonymise personal data in a staging database copy (refuses to run outside staging)';

    public function handle(): int
    {
        $db = (string) DB::connection()->getDatabaseName();
        if (app()->environment() !== 'staging' || ! str_contains(strtolower($db), 'staging')) {
            $this->error("Refusing to run: needs APP_ENV=staging and a database name containing 'staging' (env=" . app()->environment() . ", db={$db}).");

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Anonymise personal data in '{$db}'?")) {
            return self::FAILURE;
        }

        $unusable = Hash::make(bin2hex(random_bytes(16))); // nobody can log in as a copied customer
        $n = 0;

        $n += $this->scrub('users', fn ($q) => $q->update([
            'f_name' => 'Test', 'email' => DB::raw("CONCAT('user', id, '@staging.invalid')"),
            'phone' => DB::raw("CONCAT('+1555', LPAD(id, 7, '0'))"), 'l_name' => DB::raw("CONCAT('User', id)"),
            'cm_firebase_token' => null, 'password' => $unusable,
        ]), ['f_name', 'l_name', 'email', 'phone', 'cm_firebase_token', 'password']);
        $n += $this->scrub('delivery_men', fn ($q) => $q->update([
            'f_name' => 'Driver', 'l_name' => DB::raw("CONCAT('D', id)"), 'email' => DB::raw("CONCAT('driver', id, '@staging.invalid')"),
            'phone' => DB::raw("CONCAT('+1556', LPAD(id, 7, '0'))"), 'firebase_token' => null, 'auth_token' => null, 'password' => $unusable,
        ]), ['f_name', 'l_name', 'email', 'phone', 'firebase_token', 'auth_token', 'password']);
        $n += $this->scrub('vendors', fn ($q) => $q->update([
            'f_name' => 'Vendor', 'l_name' => DB::raw("CONCAT('V', id)"), 'email' => DB::raw("CONCAT('vendor', id, '@staging.invalid')"),
            'phone' => DB::raw("CONCAT('+1557', LPAD(id, 7, '0'))"), 'firebase_token' => null, 'auth_token' => null, 'password' => $unusable,
        ]), ['f_name', 'l_name', 'email', 'phone', 'firebase_token', 'auth_token', 'password']);
        $n += $this->scrub('order_anywhere_requests', fn ($q) => $q->update([
            'customer_name' => 'Test Customer', 'customer_phone' => '+15550000000', 'customer_email' => null,
            'pickup_address' => '1 Test Street', 'dropoff_address' => '2 Test Avenue',
        ]), ['customer_name', 'customer_phone', 'customer_email', 'pickup_address', 'dropoff_address']);
        $n += $this->scrub('urban_goodz_stranded_requests', fn ($q) => $q->update(['address' => '3 Test Road', 'notes' => null, 'vehicle_plate' => null]), ['address', 'notes', 'vehicle_plate']);

        // Admins keep their accounts so staging can be logged into, but lose every live session/remember token.
        $n += $this->scrub('admins', fn ($q) => $q->update(['login_remember_token' => null, 'is_logged_in' => 0]), ['login_remember_token', 'is_logged_in']);

        foreach (['oauth_access_tokens', 'oauth_refresh_tokens', 'password_resets', 'password_reset_tokens', 'failed_jobs', 'jobs', 'sessions',
            'urban_goodz_ai_conversations', 'urban_goodz_stranded_messages', 'urban_goodz_stranded_nearby_presence'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
                $this->line("cleared {$table}");
            }
        }

        $this->info("Sanitised. Tables scrubbed: {$n}. Payment provider keys in addon_settings are NOT copied here: set staging to TEST keys yourself.");

        return self::SUCCESS;
    }

    private function scrub(string $table, callable $apply, array $columns): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        foreach ($columns as $c) {
            if (! Schema::hasColumn($table, $c)) {
                $this->line("skipped {$table}: no column {$c}");

                return 0;
            }
        }
        $apply(DB::table($table));
        $this->line("scrubbed {$table}");

        return 1;
    }
}
