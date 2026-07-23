<?php
/**
 * Sanitize and privacy-scan committed test evidence.
 *
 * PHPUnit writes absolute filesystem paths into JUnit XML, which leaks the
 * operator's workstation identity and directory layout into repository
 * history. Run this over docs/qa/evidence/ after regenerating any artifact
 * and BEFORE committing.
 *
 *   php scripts/sanitize-test-evidence.php          # sanitize, then scan
 *   php scripts/sanitize-test-evidence.php --check  # scan only, no writes
 *
 * Exit code 0 = clean. Non-zero = disallowed content still present.
 */

$evidenceDir = __DIR__ . '/../docs/qa/evidence';
$checkOnly = in_array('--check', $argv, true);

if (!is_dir($evidenceDir)) {
    fwrite(STDERR, "No evidence directory at {$evidenceDir}\n");
    exit(1);
}

$repoRoot = realpath(__DIR__ . '/..');
$repoName = basename($repoRoot);

/** Patterns that must never appear in committed evidence. */
$forbidden = [
    'windows-user-path'   => '~[A-Za-z]:[\\\\/]Users[\\\\/][^\\\\/"<\s]+~i',
    'unix-home-path'      => '~/(?:home|Users)/[^/"<\s]+/~',
    'private-key-block'   => '~-----BEGIN [A-Z ]*PRIVATE KEY-----~',
    'aws-access-key'      => '~\bAKIA[0-9A-Z]{16}\b~',
    'bearer-token'        => '~\bBearer\s+[A-Za-z0-9._\-]{20,}~i',
    'password-assignment' => '~\b(?:password|passwd|secret|api_key|apikey)\s*[=:]\s*["\']?(?!\s)[^\s"\',;<]{6,}~i',
];

/** Path forms rewritten to a neutral token before scanning. */
function sanitize(string $contents, string $repoRoot, string $repoName): string
{
    $variants = [];
    foreach ([$repoRoot, str_replace('\\', '/', $repoRoot), str_replace('/', '\\', $repoRoot)] as $v) {
        $variants[$v] = true;
    }
    foreach (array_keys($variants) as $variant) {
        $contents = str_replace($variant, '[repo]', $contents);
        $contents = str_replace(htmlspecialchars($variant, ENT_QUOTES), '[repo]', $contents);
    }

    // Any remaining absolute path that still ends at the repo directory.
    $contents = preg_replace(
        '~[A-Za-z]:[\\\\/](?:[^\\\\/"<\n]+[\\\\/])*' . preg_quote($repoName, '~') . '~i',
        '[repo]',
        $contents
    );

    return $contents;
}

$files = array_values(array_filter(
    scandir($evidenceDir),
    fn ($f) => !in_array($f, ['.', '..'], true) && is_file($evidenceDir . '/' . $f)
));

$violations = [];
$rewritten = 0;

foreach ($files as $file) {
    $path = $evidenceDir . '/' . $file;
    $original = file_get_contents($path);
    $contents = $checkOnly ? $original : sanitize($original, $repoRoot, $repoName);

    if (!$checkOnly && $contents !== $original) {
        file_put_contents($path, $contents);
        $rewritten++;
        printf("sanitized  %s\n", $file);
    }

    foreach ($forbidden as $label => $pattern) {
        if (preg_match_all($pattern, $contents, $m)) {
            $sample = array_slice(array_unique($m[0]), 0, 3);
            $violations[] = sprintf('%s: %s (%d hit(s)) e.g. %s', $file, $label, count($m[0]), implode(', ', $sample));
        }
    }
}

printf("\nscanned %d file(s)%s\n", count($files), $checkOnly ? ' (check-only)' : ", rewrote {$rewritten}");

if ($violations) {
    fwrite(STDERR, "\nPRIVACY/SECRET SCAN FAILED:\n");
    foreach ($violations as $v) {
        fwrite(STDERR, "  - {$v}\n");
    }
    exit(1);
}

echo "privacy/secret scan: 0 matches - clean\n";
exit(0);
