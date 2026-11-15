<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymises a COPY of production for staging. The caller (staging:sanitize) is responsible for the safety guard
 * (staging environment + a database named *staging*); this class only does the work and then PROVES it worked.
 *
 * Design lesson: the first version listed column names by hand, one of them did not exist on the production
 * delivery_men table (fcm_token there, firebase_token elsewhere), the command skipped the whole table and still
 * printed "success", leaving 17 real drivers in staging. So now:
 *   - token columns are DISCOVERED (any column with "token" in its name on the people tables is nulled);
 *   - identity columns are scrubbed one by one when they exist (a missing column no longer skips the table);
 *   - afterwards a verification pass counts what is still real. Any leftover makes the run FAIL.
 */
class StagingSanitizer
{
    /** table => [column => value or raw SQL expression]. Only columns that exist are touched. */
    private const IDENTITY = [
        'users' => [
            'f_name' => 'Test', 'l_name' => ['raw', "CONCAT('User', id)"],
            'email' => ['raw', "CONCAT('user', id, '@staging.invalid')"], 'phone' => ['raw', "CONCAT('+1555', LPAD(id, 7, '0'))"],
        ],
        'delivery_men' => [
            'f_name' => 'Driver', 'l_name' => ['raw', "CONCAT('D', id)"],
            'email' => ['raw', "CONCAT('driver', id, '@staging.invalid')"], 'phone' => ['raw', "CONCAT('+1556', LPAD(id, 7, '0'))"],
        ],
        'vendors' => [
            'f_name' => 'Vendor', 'l_name' => ['raw', "CONCAT('V', id)"],
            'email' => ['raw', "CONCAT('vendor', id, '@staging.invalid')"], 'phone' => ['raw', "CONCAT('+1557', LPAD(id, 7, '0'))"],
        ],
        'order_anywhere_requests' => [
            'customer_name' => 'Test Customer', 'customer_phone' => '+15550000000', 'customer_email' => null,
            'pickup_address' => '1 Test Street', 'dropoff_address' => '2 Test Avenue',
        ],
        'urban_goodz_stranded_requests' => ['address' => '3 Test Road', 'notes' => null, 'vehicle_plate' => null],
    ];

    /** People tables whose every token-like column is wiped, and whose password is made unusable. */
    private const PEOPLE = ['users', 'delivery_men', 'vendors', 'admins'];

    private const CLEARED_TABLES = ['oauth_access_tokens', 'oauth_refresh_tokens', 'password_resets', 'password_reset_tokens', 'failed_jobs', 'jobs',
        'sessions', 'urban_goodz_ai_conversations', 'urban_goodz_stranded_messages', 'urban_goodz_stranded_nearby_presence'];

    /** @return array{ok: bool, log: list<string>, problems: list<string>} */
    public function run(): array
    {
        $log = [];
        $unusable = Hash::make(bin2hex(random_bytes(16)));

        foreach (self::IDENTITY as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $log[] = "no table {$table} (skipped)";
                continue;
            }
            $set = [];
            $missing = [];
            foreach ($columns as $col => $val) {
                if (! Schema::hasColumn($table, $col)) {
                    $missing[] = $col;
                    continue;
                }
                $set[$col] = is_array($val) ? DB::raw($val[1]) : $val;
            }
            if ($set) {
                DB::table($table)->update($set);
            }
            $log[] = "scrubbed {$table}: ".implode(',', array_keys($set)).($missing ? ' (no such column: '.implode(',', $missing).')' : '');
        }

        foreach (self::PEOPLE as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $tokenCols = array_values(array_filter(Schema::getColumnListing($table), fn ($c) => stripos($c, 'token') !== false));
            $set = array_fill_keys($tokenCols, null);
            if ($table !== 'admins' && Schema::hasColumn($table, 'password')) {
                $set['password'] = $unusable; // nobody can sign in as a copied customer, driver or vendor
            }
            if ($table === 'admins' && Schema::hasColumn($table, 'is_logged_in')) {
                $set['is_logged_in'] = 0; // admins keep their accounts so staging can be logged into
            }
            if ($set) {
                DB::table($table)->update($set);
            }
            $log[] = "cleared tokens on {$table}: ".($tokenCols ? implode(',', $tokenCols) : 'none found');
        }

        foreach (self::CLEARED_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
                $log[] = "cleared {$table}";
            }
        }

        $problems = $this->verify();

        return ['ok' => $problems === [], 'log' => $log, 'problems' => $problems];
    }

    /** What is STILL real? Empty means clean. @return list<string> */
    public function verify(): array
    {
        $problems = [];
        foreach (['users' => '@staging.invalid', 'delivery_men' => '@staging.invalid', 'vendors' => '@staging.invalid'] as $table => $suffix) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'email')) {
                $n = DB::table($table)->where(fn ($q) => $q->whereNull('email')->orWhere('email', 'not like', '%'.$suffix))->where('email', '!=', '')->count();
                if ($n > 0) {
                    $problems[] = "{$n} {$table} row(s) still have a real email";
                }
            }
        }
        foreach (self::PEOPLE as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (Schema::getColumnListing($table) as $col) {
                if (stripos($col, 'token') !== false) {
                    $n = DB::table($table)->whereNotNull($col)->where($col, '!=', '')->count();
                    if ($n > 0) {
                        $problems[] = "{$n} {$table} row(s) still have a value in {$col}";
                    }
                }
            }
        }

        return $problems;
    }
}
