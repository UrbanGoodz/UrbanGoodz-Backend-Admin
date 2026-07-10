<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('business_settings')->where('key', 'mail_config')->first();

        if (! $row) {
            return;
        }

        $data = json_decode($row->value, true);

        if (! $data) {
            return;
        }

        $changed = false;

        // Correct misconfigured host
        if (isset($data['host']) && $data['host'] === 'urbangoodzdelivery.com') {
            $data['host'] = 'mail.urbangoodzdelivery.com';
            $changed = true;
        }

        // Correct bare domain username to full email
        if (isset($data['username']) && $data['username'] === 'Urban Goodz') {
            $data['username'] = 'support@urbangoodzdelivery.com';
            $changed = true;
        }

        // Ensure encryption is ssl (not tls) for port 465
        if (isset($data['port']) && (string) $data['port'] === '465' && isset($data['encryption']) && $data['encryption'] === 'tls') {
            $data['encryption'] = 'ssl';
            $changed = true;
        }

        if ($changed) {
            DB::table('business_settings')
                ->where('key', 'mail_config')
                ->update([
                    'value' => json_encode($data),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally no-op — we don't want to revert correct SMTP settings
    }
};
