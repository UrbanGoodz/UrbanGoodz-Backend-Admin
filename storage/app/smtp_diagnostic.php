<?php
/**
 * SMTP Config Diagnostic Script v2
 * Run: php storage/app/smtp_diagnostic.php
 * DELETE after use - contains diagnostic output only
 */

$basePath = dirname(__DIR__, 2);
require $basePath . '/vendor/autoload.php';

$app = require_once $basePath . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Config;

echo "=== SMTP CONFIGURATION DIAGNOSTIC v2 ===\n";
echo "Date: " . now()->toDateTimeString() . "\n\n";

// 1. .env values (baseline)
echo "--- 1. .ENV VALUES (baseline) ---\n";
echo "  MAIL_HOST:       " . env('MAIL_HOST', 'NOT SET') . "\n";
echo "  MAIL_PORT:       " . env('MAIL_PORT', 'NOT SET') . "\n";
echo "  MAIL_USERNAME:   " . env('MAIL_USERNAME', 'NOT SET') . "\n";
echo "  MAIL_PASSWORD:   " . (!empty(env('MAIL_PASSWORD')) ? 'SET (hidden)' : 'NOT SET') . "\n";
echo "  MAIL_ENCRYPTION: " . env('MAIL_ENCRYPTION', 'NOT SET') . "\n";
echo "  MAIL_FROM_ADDRESS: " . env('MAIL_FROM_ADDRESS', 'NOT SET') . "\n";
echo "  MAIL_FROM_NAME:  " . env('MAIL_FROM_NAME', 'NOT SET') . "\n";

// 2. Database state
echo "\n--- 2. DATABASE BUSINESS_SETTINGS (mail_config) ---\n";
$dbState = null;
try {
    $row = BusinessSetting::where('key', 'mail_config')->first();
    if ($row) {
        $dbState = json_decode($row->value, true);
        echo "  name:       " . ($dbState['name'] ?? 'NULL') . "\n";
        echo "  driver:     " . ($dbState['driver'] ?? 'NULL') . "\n";
        echo "  host:       " . ($dbState['host'] ?? 'NULL') . "\n";
        echo "  port:       " . ($dbState['port'] ?? 'NULL') . "\n";
        echo "  username:   " . ($dbState['username'] ?? 'NULL') . "\n";
        echo "  email_id:   " . ($dbState['email_id'] ?? 'NULL') . "\n";
        echo "  encryption: " . ($dbState['encryption'] ?? 'NULL') . "\n";
        echo "  password:   " . (!empty($dbState['password']) ? 'SET (hidden)' : 'NOT SET') . "\n";
        echo "  status:     " . ($dbState['status'] ?? 'NULL') . "\n";
    } else {
        echo "  NO mail_config row found in database.\n";
    }
} catch (\Exception $e) {
    echo "  DB CONNECTION FAILED: " . $e->getMessage() . "\n";
    echo "  (ConfigServiceProvider will fall back to .env values)\n";
}

// 3. Runtime Config (after ConfigServiceProvider)
echo "\n--- 3. RUNTIME CONFIG (after ConfigServiceProvider override) ---\n";
echo "  mail.default:              " . Config::get('mail.default') . "\n";
echo "  mail.mailers.smtp.host:    " . Config::get('mail.mailers.smtp.host') . "\n";
echo "  mail.mailers.smtp.port:    " . Config::get('mail.mailers.smtp.port') . "\n";
echo "  mail.mailers.smtp.username:" . Config::get('mail.mailers.smtp.username') . "\n";
echo "  mail.mailers.smtp.password:" . (!empty(Config::get('mail.mailers.smtp.password')) ? 'SET (hidden)' : 'NOT SET') . "\n";
echo "  mail.mailers.smtp.encryption: " . Config::get('mail.mailers.smtp.encryption') . "\n";
echo "  mail.from.address:         " . Config::get('mail.from.address') . "\n";
echo "  mail.from.name:            " . Config::get('mail.from.name') . "\n";

// 4. Validation
echo "\n--- 4. EXPECTED vs RUNTIME ---\n";
$expected = [
    'host'       => 'mail.urbangoodzdelivery.com',
    'port'       => '465',
    'username'   => 'support@urbangoodzdelivery.com',
    'email_id'   => 'support@urbangoodzdelivery.com',
    'encryption' => 'ssl',
    'driver'     => 'smtp',
    'name'       => 'Urban Goodz',
];

$runtimeMap = [
    'host'       => 'mail.mailers.smtp.host',
    'port'       => 'mail.mailers.smtp.port',
    'username'   => 'mail.mailers.smtp.username',
    'email_id'   => 'mail.from.address',
    'encryption' => 'mail.mailers.smtp.encryption',
    'driver'     => 'mail.default',
    'name'       => 'mail.from.name',
];

$allMatch = true;
foreach ($expected as $key => $exp) {
    $actual = Config::get($runtimeMap[$key]);
    $match = ((string) $actual === (string) $exp) ? 'MATCH' : 'MISMATCH';
    if ($match === 'MISMATCH') $allMatch = false;
    echo "  {$key}: expected=[{$exp}] actual=[{$actual}] => {$match}\n";
}

// 5. SMTP Connection Test (proper multi-line handling)
echo "\n--- 5. SMTP CONNECTION TEST ---\n";
$host = Config::get('mail.mailers.smtp.host');
$port = (int) Config::get('mail.mailers.smtp.port');
$username = Config::get('mail.mailers.smtp.username');
$password = Config::get('mail.mailers.smtp.password');
$encryption = Config::get('mail.mailers.smtp.encryption');

echo "  Target: {$host}:{$port} ({$encryption})\n";

function smtpReadLine($fp) {
    $line = fgets($fp, 4096);
    return $line;
}

function smtpReadMultiLine($fp) {
    $lines = [];
    $line = fgets($fp, 4096);
    $lines[] = $line;
    // Multi-line: code followed by '-' means more lines coming
    while (isset($line[3]) && $line[3] === '-') {
        $line = fgets($fp, 4096);
        $lines[] = $line;
    }
    return $lines;
}

$errno = 0;
$errstr = '';
$timeout = 10;

$context = stream_context_create([
    'ssl' => [
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ],
]);

$fp = @stream_socket_client(
    "tcp://{$host}:{$port}",
    $errno,
    $errstr,
    $timeout,
    STREAM_CLIENT_CONNECT,
    $context
);

if (!$fp) {
    echo "  TCP Connection: FAILED\n";
    echo "  Error: [{$errno}] {$errstr}\n";
    echo "\n=== END DIAGNOSTIC ===\n";
    exit(1);
}

echo "  TCP Connection: SUCCESS\n";

if ($encryption === 'ssl' || $encryption === 'tls') {
    $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
    echo "  TLS Encryption: " . ($crypto === true ? 'SUCCESS' : 'FAILED (code=' . var_export($crypto, true) . ')') . "\n";
    if ($crypto !== true) {
        echo "  Trying without encryption for diagnosis...\n";
    }
}

// SMTP Banner (multi-line)
$banner = smtpReadMultiLine($fp);
echo "  SMTP Banner: " . trim(implode('', $banner)) . "\n";

// EHLO
@fwrite($fp, "EHLO localhost\r\n");
$ehlo = smtpReadMultiLine($fp);
echo "  EHLO Response: " . trim(implode('', $ehlo)) . "\n";

// AUTH LOGIN
@fwrite($fp, "AUTH LOGIN\r\n");
$authResp = smtpReadMultiLine($fp);
$authCode = substr(trim(implode('', $authResp)), 0, 3);
echo "  AUTH LOGIN: " . trim(implode('', $authResp)) . "\n";

if ($authCode === '334') {
    // Username (base64)
    @fwrite($fp, base64_encode($username) . "\r\n");
    $userResp = smtpReadMultiLine($fp);
    echo "  Username Response: " . trim(implode('', $userResp)) . "\n";

    // Password (base64)
    @fwrite($fp, base64_encode($password) . "\r\n");
    $passResp = smtpReadMultiLine($fp);
    echo "  Password Response: " . trim(implode('', $passResp)) . "\n";

    $authSuccess = strpos($passResp[0], '235') !== false;
    echo "  Authentication: " . ($authSuccess ? 'SUCCESS' : 'FAILED') . "\n";
    $authResult = $authSuccess ? 'SUCCESS' : 'FAILED';
} else {
    echo "  AUTH LOGIN not accepted (code: {$authCode}). Server may not support LOGIN auth.\n";
    $authResult = 'NOT_ACCEPTED';
}

// QUIT
@fwrite($fp, "QUIT\r\n");
@fgets($fp, 512);
@fclose($fp);

// Summary
echo "\n--- 6. SUMMARY ---\n";
echo "  Config Match:     " . ($allMatch ? 'ALL MATCH' : 'MISMATCH DETECTED') . "\n";
echo "  SMTP Connection:  OK\n";
echo "  SMTP Auth:        {$authResult}\n";
echo "  Password Status:  " . (!empty($password) ? 'SET (hidden)' : 'NOT SET') . "\n";

if (!$allMatch || $authResult !== 'SUCCESS') {
    echo "\n  ACTION REQUIRED:\n";
    if (!$allMatch) {
        echo "  - Fix mismatched config values (see section 4)\n";
    }
    if ($authResult !== 'SUCCESS') {
        echo "  - Verify SMTP password is correct (owner must check in admin panel)\n";
        echo "  - Verify host is correct cPanel SMTP server (mail.urbangoodzdelivery.com)\n";
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
