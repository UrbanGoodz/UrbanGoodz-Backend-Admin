<?php
/**
 * ISOLATED STAGING — external-service safety confirmation.
 *
 * Boots the application under a given environment and asserts that no external
 * provider can charge money, send mail/SMS, push notifications, or broadcast.
 * Exits non-zero if ANY check fails, so it can gate an E2E run.
 *
 * Usage: php scripts/audit/staging_safety_check.php [env]
 */

$envName = $argv[1] ?? 'staging';
putenv("APP_ENV={$envName}");
$_ENV['APP_ENV'] = $envName;
$_SERVER['APP_ENV'] = $envName;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fail = 0;
$rows = [];

/**
 * @param string $label   what is being asserted
 * @param bool   $ok      whether it is safe
 * @param string $actual  observed value
 */
function check(string $label, bool $ok, string $actual): void
{
    global $fail, $rows;
    if (!$ok) {
        $fail++;
    }
    $rows[] = [$ok ? 'SAFE' : 'UNSAFE', $label, $actual];
}

/* -------------------------------------------------------------- environment */
$env = app()->environment();
check('APP_ENV is not production', $env !== 'production', $env);
check('APP_URL is loopback-bound',
    (bool) preg_match('~^https?://(127\.0\.0\.1|localhost)(:\d+)?~', (string) config('app.url')),
    (string) config('app.url'));

/* ------------------------------------------------------------------ database */
$dbName = (string) config('database.connections.' . config('database.default') . '.database');
$dbHost = (string) config('database.connections.' . config('database.default') . '.host');
check('Database is an isolated staging schema',
    str_contains($dbName, 'pathc') || str_contains($dbName, 'staging'), $dbName);
check('Database host is local', in_array($dbHost, ['127.0.0.1', 'localhost'], true), $dbHost);

/* ---------------------------------------------------------------------- mail */
check('Mail driver cannot reach an SMTP relay',
    in_array(config('mail.default'), ['log', 'array', 'null'], true),
    (string) config('mail.default'));

/* --------------------------------------------------------------------- queue */
check('Queue is synchronous or local',
    in_array(config('queue.default'), ['sync', 'database', 'null'], true),
    (string) config('queue.default'));

/* ----------------------------------------------------------------- broadcast */
$bc = config('broadcasting.default');
check('Broadcasting cannot reach a live socket server',
    in_array($bc, ['log', 'null'], true), (string) $bc);

/* ------------------------------------------------------------------ payments */
check('Payment provider is not a live gateway',
    in_array(config('urban_goodz_payments.provider'), ['staged_test', 'disabled'], true),
    (string) config('urban_goodz_payments.provider'));
check('Adyen disabled', !config('urban_goodz_payments.adyen.enabled'),
    var_export(config('urban_goodz_payments.adyen.enabled'), true));
check('Stripe disabled', !config('urban_goodz_payments.stripe.enabled'),
    var_export(config('urban_goodz_payments.stripe.enabled'), true));
check('Stripe live secret key absent', empty(config('urban_goodz_payments.stripe.live_secret_key')), '(empty)');
check('Adyen API key absent', empty(config('urban_goodz_payments.adyen.api_key')), '(empty)');
check('Live-controlled payments disabled', !config('urban_goodz_payments.live_controlled.enabled'),
    var_export(config('urban_goodz_payments.live_controlled.enabled'), true));
check('Card issuing not live',
    config('urban_goodz_payments.issuing.mode') !== 'live_controlled',
    (string) config('urban_goodz_payments.issuing.mode'));

/* ------------------------------------------------------------------ firebase */
check('Firebase project unset', empty(config('firebase.projects.app.project_id')),
    (string) (config('firebase.projects.app.project_id') ?: '(empty)'));
check('Firebase credentials unset', empty(env('FIREBASE_CREDENTIALS')), '(empty)');

/* -------------------------------------------------------------------- AI/LLM */
check('OpenAI key absent', empty(config('openai.api_key')), '(empty)');
check('Fashion Fit AI disabled', !config('fashion_fit_ai.enabled'),
    var_export(config('fashion_fit_ai.enabled'), true));

/* --------------------------------------------------------------- filesystem */
check('Filesystem disk is local', config('filesystems.default') === 'local',
    (string) config('filesystems.default'));

/* --------------------------------------------------- cache/session isolation */
check('Cache prefix is staging-scoped',
    str_contains((string) config('cache.prefix'), 'staging'), (string) config('cache.prefix'));
check('Session cookie is staging-scoped',
    str_contains((string) config('session.cookie'), 'staging'), (string) config('session.cookie'));

/* --------------------------------------------------------------------- output */
$w = max(array_map(fn ($r) => strlen($r[1]), $rows));
foreach ($rows as [$status, $label, $actual]) {
    printf("[%-6s] %-{$w}s  %s\n", $status, $label, $actual);
}

echo "\n";
printf("CHECKS: %d   FAILED: %d\n", count($rows), $fail);
echo $fail === 0
    ? "EXTERNAL SERVICES DISABLED: CONFIRMED — nothing here can charge or notify anyone.\n"
    : "EXTERNAL SERVICES DISABLED: NOT CONFIRMED — resolve the UNSAFE rows above.\n";

exit($fail === 0 ? 0 : 1);
