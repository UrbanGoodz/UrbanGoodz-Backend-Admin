<?php

namespace App\Console\Commands;

use App\Services\StagingSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scrubs a COPY of production so it can be used as a staging database. Refuses to run unless the app is in the
 * "staging" environment AND the database name contains "staging", so it can never be pointed at production by
 * accident. Exits with a failure (and says what is left) if anything real survives the scrub.
 */
class SanitizeForStaging extends Command
{
    protected $signature = 'staging:sanitize {--force : Skip the confirmation prompt}';

    protected $description = 'Anonymise personal data in a staging database copy and verify it (refuses to run outside staging)';

    public function handle(StagingSanitizer $sanitizer): int
    {
        $db = (string) DB::connection()->getDatabaseName();
        if (app()->environment() !== 'staging' || ! str_contains(strtolower($db), 'staging')) {
            $this->error("Refusing to run: needs APP_ENV=staging and a database name containing 'staging' (env=" . app()->environment() . ", db={$db}).");

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Anonymise personal data in '{$db}'?")) {
            return self::FAILURE;
        }

        $result = $sanitizer->run();
        foreach ($result['log'] as $line) {
            $this->line($line);
        }

        if (! $result['ok']) {
            $this->error('SANITISE FAILED. Real data is still present:');
            foreach ($result['problems'] as $p) {
                $this->error('  - '.$p);
            }

            return self::FAILURE;
        }

        $this->info('Sanitised and verified: no real emails or login/device tokens remain on users, drivers, vendors or admins. '
            .'Payment provider keys in addon_settings are NOT copied here: set staging to TEST keys yourself.');

        return self::SUCCESS;
    }
}
