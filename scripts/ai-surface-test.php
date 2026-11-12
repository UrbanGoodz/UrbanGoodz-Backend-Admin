<?php
/**
 * Exercise the AI surface as a signed-in admin.
 *
 *   php scripts/ai-surface-test.php
 *
 * The assertion is NOT that the model answers - the provider is quota limited
 * and returns 429/503 under load - but that every AI page renders and that a
 * live generation degrades instead of returning 500 when the provider is down.
 *
 * Provider state as measured on 2026-09-14:
 *   primary  gemini / gemini-flash-latest  - key valid, model valid, answers
 *   fallback openai / gpt-4o-mini          - rate_limit_exceeded, no protection
 *
 * LOCAL/TEST DATABASES ONLY. Uses the pw.full fixture admin.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$base = 'http://127.0.0.1:8080/';
$jar  = 'C:/javatmp/ai_jar.txt';
@unlink($jar);

function req(string $url, array $opt = []): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
    ] + $opt);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}

[, $page] = req($base . 'login/admin');
preg_match('/name="_token" value="([^"]+)"/', $page, $t);
preg_match('/id="custome_recaptcha"[^>]*value="([^"]*)"/s', $page, $c);
req($base . 'login_submit', [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query([
    '_token' => $t[1] ?? '', 'email' => 'pw.full@urbangoodz.test', 'password' => 'PwFixture!2026',
    'custome_recaptcha' => $c[1] ?? '', 'set_default_captcha' => 1, 'role' => 'admin',
])]);
[$code] = req($base . 'admin');
echo "admin login: HTTP {$code}\n\n";
if ($code !== 200) { fwrite(STDERR, "login failed\n"); exit(3); }

// Read-only AI surfaces an admin actually opens.
$pages = [
    'admin/urban-goodz/ai-operations',
    'admin/urban-goodz/ai-operations/feature-controls',
    'admin/urban-goodz/ai-operations/logs',
    'admin/urban-goodz/ai-operations/usage',
    'admin/urban-goodz/ai-copilot',
    'admin/urban-goodz/ai-copilot/settings',
    'admin/urban-goodz/ai-copilot/risk-rules',
    'admin/urban-goodz/ai-copilot/suppressed',
    'admin/urban-goodz/ai-copilot/action-logs',
    'admin/urban-goodz/ai-copilot/module-settings',
    'admin/urban-goodz/ai-copilot/load-board-analytics',
    'admin/urban-goodz/ai-chief-of-staff',
    'admin/urban-goodz/ai-chief-of-staff/notifications',
    'admin/urban-goodz/ai-concierge/intents',
    'admin/urban-goodz/ai-concierge/conversations',
    'admin/business-settings/open-ai-settings',
    'admin/business-settings/open-ai-config-status',
];

$bad = 0;
echo "--- AI admin pages ---\n";
foreach ($pages as $p) {
    [$code] = req($base . $p);
    $flag = $code >= 500 ? '  <-- 5xx' : '';
    if ($code >= 500) $bad++;
    printf("  %-52s %d%s\n", $p, $code, $flag);
}

// A live generation path: must degrade, never 500, when the provider is down.
echo "\n--- live AI generation (provider is quota limited) ---\n";
// Laravel needs the CSRF token from a rendered admin page, else 419.
[, $dash] = req($base . 'admin');
preg_match('/name="csrf-token" content="([^"]+)"/', $dash, $m)
    || preg_match('/name="_token" value="([^"]+)"/', $dash, $m);
$csrf = $m[1] ?? '';

[$code, $body] = req($base . 'admin/urban-goodz/ai-chief-of-staff/query', [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['X-CSRF-TOKEN: ' . $csrf, 'X-Requested-With: XMLHttpRequest', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => http_build_query([
        '_token' => $csrf,
        'prompt' => 'How many orders were delivered today?',
    ]),
]);
$json = json_decode($body, true);
printf("  chief-of-staff/query   HTTP %d%s\n", $code, $code >= 500 ? '  <-- 5xx' : '');
if (is_array($json)) {
    foreach (['success', 'error_code', 'provider', 'message', 'answer', 'response'] as $k) {
        if (!isset($json[$k])) continue;
        $v = is_scalar($json[$k]) ? (string) $json[$k] : json_encode($json[$k]);
        printf("    %-11s %s\n", $k, strlen($v) > 90 ? substr($v, 0, 90) . '…' : $v);
    }
}
if ($code >= 500) $bad++;

printf("\nAI endpoints returning 5xx: %d\n", $bad);
exit($bad > 0 ? 1 : 0);
