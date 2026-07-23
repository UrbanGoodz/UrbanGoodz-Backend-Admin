<?php
/**
 * Deterministic scrub-verification gate for committed test-runner artifacts.
 *
 * Run against the exact files about to be staged, before any documentation
 * claims "zero remaining matches" — the prior two rounds of manual scrubbing
 * on this branch each missed real fragments because the verification pattern
 * required more of the string than a truncated fragment actually contained
 * (e.g. requiring the literal substring "Andre" when PHP had already cut the
 * text off at "Andr"). This script does not assume where a path was cut off:
 * it flags "C:\Users" with zero, one, or two trailing backslashes and any
 * run of non-quote characters after it, which covers full paths and every
 * truncation point PHP/PHPUnit's own argument-preview formatter can produce.
 *
 * Usage: php test-support/reports/verify-no-local-paths.php <file> [<file> ...]
 * Exit 0 = clean. Exit 1 = at least one match found (also prints each hit).
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php verify-no-local-paths.php <file> [<file> ...]\n");
    exit(2);
}

$bs = chr(92);
$quote1 = chr(39);
$quote2 = chr(34);

// Any "C:" + optional backslashes + "Users" + optional backslashes, regardless
// of what (if anything) follows — a bare "C:\Users" with nothing after it is
// still a local machine fragment worth catching, not just name-bearing ones.
$patterns = [
    'Windows user-profile path (C:\\Users..., any truncation point)' =>
        '/C:' . preg_quote($bs, '/') . '{0,2}Users' . preg_quote($bs, '/') . '{0,2}/',
    // Belt-and-suspenders: catches a bare drive-letter-colon-backslash sequence
    // immediately followed by a capitalized word, in case "Users" itself was
    // truncated away (e.g. "C:\Use...").
    'Bare Windows drive path (C:\\<Capitalized>...)' =>
        '/[A-Za-z]:' . preg_quote($bs, '/') . '{1,2}[A-Z][a-z]*/',
];

$failed = false;

foreach (array_slice($argv, 1) as $file) {
    if (!file_exists($file)) {
        fwrite(STDERR, "MISSING: $file\n");
        $failed = true;
        continue;
    }

    $content = file_get_contents($file);
    $lines = explode("\n", $content);

    foreach ($patterns as $label => $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$match, $offset]) {
                $lineNo = substr_count($content, "\n", 0, $offset) + 1;
                echo "FAIL: $file:$lineNo — [$label] matched: " . trim($match) . "\n";
                $failed = true;
            }
        }
    }
}

if ($failed) {
    fwrite(STDERR, "\nAt least one local-path fragment found. Do not commit until this exits clean.\n");
    exit(1);
}

echo "OK: no local-path fragments found in " . (count($argv) - 1) . " file(s).\n";
exit(0);
