<?php

use App\Services\MailRuntimeConfiguration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('business_settings')->where('key', 'mail_config')->first();
        $data = $row?->value ? json_decode($row->value, true) : null;

        if (! is_array($data) || empty($data['password'])) {
            return;
        }

        $service = app(MailRuntimeConfiguration::class);
        if ($service->isEncrypted((string) $data['password'])) {
            return;
        }

        $data['password'] = $service->encryptPassword((string) $data['password']);

        DB::table('business_settings')->where('key', 'mail_config')->update([
            'value' => json_encode($data),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Credentials must never be migrated back to plaintext.
    }
};
