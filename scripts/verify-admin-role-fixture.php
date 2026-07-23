<?php
/**
 * Read-only verification of the Playwright Admin role fixture pair.
 *
 * The browser suite cannot read admin_roles.modules, so it can only infer
 * permissions from rendered links -- permissions that render no link are
 * invisible to it. This script reads the authoritative stored data instead
 * and emits a sanitized attestation the browser preflight then requires.
 *
 * Run this ON THE STAGING HOST (it needs that database), then commit or
 * hand over the generated attestation:
 *
 *   php scripts/verify-admin-role-fixture.php \
 *       --authorized=ops.authorized@example.test \
 *       --restricted=ops.restricted@example.test
 *
 * It performs SELECT queries only -- no writes, no schema changes.
 * Emails are never stored in the output; only a truncated SHA-256 so the
 * browser suite can confirm it is testing the same accounts.
 *
 * Exit 0 = fixture valid. Non-zero = fixture unusable for certification.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

const REQUIRED_MODULE = 'urban_goodz_view';
const OUTPUT = __DIR__ . '/../docs/qa/evidence/role-fixture-verification.json';

function arg(string $name): ?string
{
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return null;
}

/** Stable, non-reversible account reference for the attestation. */
function accountRef(string $email): string
{
    return substr(hash('sha256', strtolower(trim($email))), 0, 16);
}

function fail(string $message): never
{
    fwrite(STDERR, "FIXTURE INVALID: {$message}\n");
    exit(1);
}

$authorizedEmail = arg('authorized');
$restrictedEmail = arg('restricted');

if (!$authorizedEmail || !$restrictedEmail) {
    fwrite(STDERR, "Usage: php scripts/verify-admin-role-fixture.php --authorized=EMAIL --restricted=EMAIL\n");
    exit(2);
}

/** @return array{role_id:int|null, modules:array} */
function loadAdmin(string $email): array
{
    $admin = DB::table('admins')->where('email', $email)->first(['id', 'role_id']);
    if (!$admin) {
        fail("no admin row found for the supplied {$email} account reference");
    }

    $modules = [];
    if ($admin->role_id !== null) {
        $role = DB::table('admin_roles')->where('id', $admin->role_id)->first(['modules']);
        $decoded = $role ? json_decode((string) $role->modules, true) : null;
        $modules = is_array($decoded) ? $decoded : [];
    }

    sort($modules);
    return ['role_id' => $admin->role_id, 'modules' => $modules];
}

$authorized = loadAdmin($authorizedEmail);
$restricted = loadAdmin($restrictedEmail);

$problems = [];

// 1. Neither may be the primary Admin: role_id == 1 short-circuits
//    Helpers::module_permission_check() and would prove nothing.
foreach (['authorized' => $authorized, 'restricted' => $restricted] as $label => $acct) {
    if ((int) $acct['role_id'] === 1) {
        $problems[] = "{$label} account is the primary Admin (role_id=1)";
    }
    if ($acct['role_id'] === null) {
        $problems[] = "{$label} account has no role assigned";
    }
}

// 2. The two roles must differ by exactly urban_goodz_view.
$onlyAuthorized = array_values(array_diff($authorized['modules'], $restricted['modules']));
$onlyRestricted = array_values(array_diff($restricted['modules'], $authorized['modules']));
$symmetric = array_values(array_unique(array_merge($onlyAuthorized, $onlyRestricted)));
sort($symmetric);

if ($symmetric !== [REQUIRED_MODULE]) {
    $problems[] = 'symmetric module difference is ' . json_encode($symmetric)
        . ', expected exactly ["' . REQUIRED_MODULE . '"]';
}
if (!in_array(REQUIRED_MODULE, $authorized['modules'], true)) {
    $problems[] = 'authorized account role does not contain ' . REQUIRED_MODULE;
}
if (in_array(REQUIRED_MODULE, $restricted['modules'], true)) {
    $problems[] = 'restricted account role unexpectedly contains ' . REQUIRED_MODULE;
}

$attestation = [
    'generated_at' => gmdate('c'),
    'verdict' => $problems ? 'FAIL' : 'PASS',
    'problems' => $problems,
    'required_module' => REQUIRED_MODULE,
    'authorized' => [
        'account_ref' => accountRef($authorizedEmail),
        'role_id_is_primary' => (int) $authorized['role_id'] === 1,
        'module_count' => count($authorized['modules']),
    ],
    'restricted' => [
        'account_ref' => accountRef($restrictedEmail),
        'role_id_is_primary' => (int) $restricted['role_id'] === 1,
        'module_count' => count($restricted['modules']),
    ],
    'symmetric_module_difference' => $symmetric,
];

@mkdir(dirname(OUTPUT), 0775, true);
file_put_contents(OUTPUT, json_encode($attestation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo "wrote " . realpath(OUTPUT) . "\n";
echo json_encode($attestation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($problems) {
    fwrite(STDERR, "\nFIXTURE INVALID:\n  - " . implode("\n  - ", $problems) . "\n");
    exit(1);
}

echo "\nfixture valid: both accounts non-primary, differing by exactly " . REQUIRED_MODULE . "\n";
exit(0);
