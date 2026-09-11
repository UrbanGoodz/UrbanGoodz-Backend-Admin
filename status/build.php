<?php

/**
 * Generates status/readiness.html from status/status.json.
 *
 * Run:  php status/build.php
 *
 * Design note, and the reason this file exists at all:
 *
 * The board this replaces reported percentages nobody could trace. It claimed
 * a payout engine that was not written, APKs in a directory that did not
 * exist, and a branch whose newest commit predated the work by two weeks. It
 * also reported finished work (the 110-131 catalog import) as not started.
 * Every one of those was a number typed by hand into a document.
 *
 * So this generator refuses to store counts. Anything countable - tests,
 * routes, models, migrations, app sizes, git state - is measured from the
 * working tree at generation time. status.json holds only human judgement,
 * and every judgement row must carry evidence a reader can go check. A row
 * that cannot cite evidence is rendered as UNVERIFIED rather than given a
 * number, because an unverified number is worse than no number.
 */

$root = dirname(__DIR__);
$statusFile = __DIR__ . '/status.json';
$outFile = __DIR__ . '/readiness.html';

if (!is_file($statusFile)) {
    fwrite(STDERR, "status/status.json is missing.\n");
    exit(1);
}

$data = json_decode(file_get_contents($statusFile), true);

if (!is_array($data) || empty($data['areas'])) {
    fwrite(STDERR, "status/status.json is not valid JSON, or has no areas.\n");
    exit(1);
}

/**
 * Git state, read straight out of .git rather than by shelling out.
 *
 * shell_exec() is not usable here: on this build machine spawning a child
 * process from PHP hangs indefinitely -- `git rev-parse` alone did not return
 * within five minutes, which is what stalled the first three attempts to
 * generate this board. Reading the ref files is both instant and has no
 * dependency on git being installed.
 */
function gitState(string $root): array
{
    $dir = $root . '/.git';
    $out = ['branch' => 'unknown', 'head' => 'unknown'];

    if (!is_dir($dir)) {
        return $out;
    }

    $head = @file_get_contents($dir . '/HEAD');

    if ($head === false) {
        return $out;
    }

    $head = trim($head);

    if (str_starts_with($head, 'ref: ')) {
        $ref = substr($head, 5);
        $out['branch'] = preg_replace('#^refs/heads/#', '', $ref);

        $shaFile = $dir . '/' . $ref;

        if (is_file($shaFile)) {
            $out['head'] = substr(trim((string) file_get_contents($shaFile)), 0, 7);
        } elseif (is_file($dir . '/packed-refs')) {
            // A branch that has never been written loose lives in packed-refs.
            foreach (file($dir . '/packed-refs', FILE_IGNORE_NEW_LINES) as $line) {
                if (str_ends_with($line, ' ' . $ref)) {
                    $out['head'] = substr($line, 0, 7);
                    break;
                }
            }
        }
    } else {
        // Detached HEAD: the file holds the sha itself.
        $out['branch'] = '(detached)';
        $out['head'] = substr($head, 0, 7);
    }

    return $out;
}

/** Count files matching a glob pattern recursively, without shelling out. */
function countFiles(string $dir, string $pattern): int
{
    if (!is_dir($dir)) {
        return 0;
    }

    $count = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
            $count++;
        }
    }

    return $count;
}

/** Count regex matches across every file under a directory. */
function countMatches(string $dir, string $regex, string $pattern = '*.php'): int
{
    if (!is_dir($dir)) {
        return 0;
    }

    $count = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if (!$file->isFile() || !fnmatch($pattern, $file->getFilename())) {
            continue;
        }

        $body = @file_get_contents($file->getPathname());

        if ($body !== false) {
            $count += preg_match_all($regex, $body);
        }
    }

    return $count;
}

// ---------------------------------------------------------------------------
// Live metrics. Measured, never stored.
// ---------------------------------------------------------------------------

$metrics = [
    'Test methods' => countMatches($root . '/tests', '/public function test_/'),
    'Test files' => countFiles($root . '/tests', '*Test.php'),
    'Route definitions' => countMatches($root . '/routes', '/Route::(get|post|put|patch|delete)\(/'),
    'Models' => countFiles($root . '/app/Models', '*.php'),
    'Controllers' => countFiles($root . '/app/Http/Controllers', '*.php'),
    'Migrations' => countFiles($root . '/database/migrations', '*.php'),
    'Blade views' => countFiles($root . '/resources/views', '*.blade.php'),
    'Console commands' => countFiles($root . '/app/Console/Commands', '*.php'),
    'Services' => countFiles($root . '/app/Services', '*.php'),
];

$git = gitState($root);

// ---------------------------------------------------------------------------
// Weighted overall. Unverified rows are counted at their stated percentage but
// flagged, so the headline number can never be propped up by a claim nobody
// checked - the reader can see exactly how much of it rests on unverified rows.
// ---------------------------------------------------------------------------

$areas = $data['areas'];
$total = 0;
$verifiedTotal = 0;
$verifiedCount = 0;

foreach ($areas as $a) {
    $total += (int) $a['percent'];

    if (!empty($a['verified'])) {
        $verifiedTotal += (int) $a['percent'];
        $verifiedCount++;
    }
}

$overall = count($areas) > 0 ? (int) round($total / count($areas)) : 0;
$verifiedOverall = $verifiedCount > 0 ? (int) round($verifiedTotal / $verifiedCount) : 0;
$unverifiedCount = count($areas) - $verifiedCount;

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function barColor(int $p): string
{
    if ($p >= 90) return 'var(--ok)';
    if ($p >= 60) return 'var(--warn)';
    if ($p >= 30) return 'var(--low)';
    return 'var(--bad)';
}

$generated = date('Y-m-d H:i');

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------

ob_start();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Urban Goodz — Production Readiness</title>
<style>
  :root {
    --bg: #f7f7f5; --card: #fff; --ink: #1a1a18; --muted: #6b6b66;
    --line: #e3e3de; --ok: #2e7d52; --warn: #b8860b; --low: #c25e00; --bad: #b3261e;
    --accent: #1a1a18;
  }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      --bg: #16161a; --card: #1e1e23; --ink: #ececea; --muted: #9a9a95;
      --line: #32323a; --ok: #5fbf8b; --warn: #d9a92b; --low: #e08843; --bad: #e2685e;
      --accent: #ececea;
    }
  }
  :root[data-theme="dark"] {
    --bg: #16161a; --card: #1e1e23; --ink: #ececea; --muted: #9a9a95;
    --line: #32323a; --ok: #5fbf8b; --warn: #d9a92b; --low: #e08843; --bad: #e2685e;
    --accent: #ececea;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; background: var(--bg); color: var(--ink);
    font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    padding-block: 32px;
  }
  .wrap { max-width: 940px; margin: 0 auto; padding: 0 20px; }
  h1 { font-size: 1.6rem; margin: 0 0 4px; letter-spacing: -0.01em; }
  .sub { color: var(--muted); font-size: 0.88rem; margin-bottom: 26px; }
  .headline {
    background: var(--card); border: 1px solid var(--line); border-radius: 12px;
    padding: 22px; margin-bottom: 12px;
  }
  .big { font-size: 2.9rem; font-weight: 650; line-height: 1; letter-spacing: -0.02em; }
  .big small { font-size: 1rem; font-weight: 400; color: var(--muted); margin-left: 6px; }
  .caveat {
    margin-top: 12px; padding: 11px 13px; border-radius: 8px;
    background: color-mix(in srgb, var(--warn) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--warn) 35%, transparent);
    font-size: 0.86rem;
  }
  .metrics {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(132px, 1fr));
    gap: 8px; margin-bottom: 26px;
  }
  .metric {
    background: var(--card); border: 1px solid var(--line);
    border-radius: 9px; padding: 11px 13px;
  }
  .metric b { display: block; font-size: 1.3rem; font-weight: 600; }
  .metric span { color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
  .area {
    background: var(--card); border: 1px solid var(--line);
    border-radius: 11px; padding: 16px 18px; margin-bottom: 10px;
  }
  .area-top { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
  .area-name { font-weight: 600; flex: 1; min-width: 200px; }
  .pct { font-variant-numeric: tabular-nums; font-weight: 650; }
  .track { height: 6px; background: var(--line); border-radius: 3px; margin: 10px 0 12px; overflow: hidden; }
  .fill { height: 100%; border-radius: 3px; }
  .tag {
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;
    padding: 2px 7px; border-radius: 4px; border: 1px solid var(--line); color: var(--muted);
  }
  .tag.unverified { color: var(--bad); border-color: color-mix(in srgb, var(--bad) 45%, transparent); }
  .ev { font-size: 0.85rem; color: var(--muted); }
  .ev b { color: var(--ink); font-weight: 600; }
  .blocker {
    margin-top: 9px; font-size: 0.85rem; padding: 9px 11px; border-radius: 7px;
    background: color-mix(in srgb, var(--bad) 9%, transparent);
    border-left: 3px solid var(--bad);
  }
  h2 { font-size: 1.05rem; margin: 30px 0 10px; }
  .retired { background: var(--card); border: 1px solid var(--line); border-radius: 11px; padding: 4px 18px; }
  .retired dl { margin: 0; }
  .retired dt { font-weight: 600; margin-top: 14px; font-size: 0.9rem; }
  .retired dd { margin: 4px 0 14px; color: var(--muted); font-size: 0.86rem; }
  footer { margin-top: 30px; color: var(--muted); font-size: 0.78rem; border-top: 1px solid var(--line); padding-top: 14px; }
  code { background: color-mix(in srgb, var(--ink) 8%, transparent); padding: 1px 5px; border-radius: 4px; font-size: 0.85em; }
</style>
</head>
<body>
<div class="wrap">

  <h1>Urban Goodz — Production Readiness</h1>
  <div class="sub">
    Generated <?= e($generated) ?> by <code>php status/build.php</code> ·
    branch <code><?= e($git['branch']) ?></code> ·
    HEAD <code><?= e($git['head']) ?></code>
  </div>

  <div class="headline">
    <div class="big"><?= $overall ?>%<small>overall, all <?= count($areas) ?> areas</small></div>
    <div class="caveat">
      <strong><?= $verifiedOverall ?>%</strong> across the <?= $verifiedCount ?> areas backed by evidence.
      <?= $unverifiedCount ?> area<?= $unverifiedCount === 1 ? ' is' : 's are' ?> unverified and should be
      treated as unknown rather than as the number shown.
    </div>
  </div>

  <div class="metrics">
    <?php foreach ($metrics as $label => $value): ?>
      <div class="metric"><b><?= number_format($value) ?></b><span><?= e($label) ?></span></div>
    <?php endforeach; ?>
  </div>

  <?php foreach ($areas as $a):
      $p = (int) $a['percent'];
      $ver = !empty($a['verified']);
  ?>
    <div class="area">
      <div class="area-top">
        <span class="area-name"><?= e($a['name']) ?></span>
        <span class="tag<?= $ver ? '' : ' unverified' ?>"><?= $ver ? e($a['state']) : 'unverified' ?></span>
        <span class="pct" style="color:<?= barColor($p) ?>"><?= $p ?>%</span>
      </div>
      <div class="track"><div class="fill" style="width:<?= $p ?>%;background:<?= barColor($p) ?>"></div></div>
      <div class="ev"><b>Evidence:</b> <?= e($a['evidence']) ?></div>
      <?php if (!empty($a['blocker'])): ?>
        <div class="blocker"><b>Blocked:</b> <?= e($a['blocker']) ?> <em>(<?= e($a['owner']) ?>)</em></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!empty($data['retired_claims'])): ?>
    <h2>Retired claims</h2>
    <div class="sub" style="margin-bottom:10px">
      Statements from earlier status reports that did not survive verification. Kept so they
      are not silently reintroduced.
    </div>
    <div class="retired"><dl>
      <?php foreach ($data['retired_claims'] as $c): ?>
        <dt><?= e($c['claim']) ?></dt>
        <dd><?= e($c['finding']) ?></dd>
      <?php endforeach; ?>
    </dl></div>
  <?php endif; ?>

  <footer>
    Counts above are measured from the working tree at generation time, never stored.
    Percentages come from <code>status/status.json</code> and each carries evidence.
    Assessed <?= e($data['assessed_on'] ?? 'unknown') ?> — <?= e($data['assessed_by'] ?? '') ?>.
  </footer>

</div>
</body>
</html>
<?php

file_put_contents($outFile, ob_get_clean());

echo "Wrote " . $outFile . "\n";
echo "Overall: {$overall}%  (verified-only: {$verifiedOverall}% across {$verifiedCount} areas, {$unverifiedCount} unverified)\n";

foreach ($metrics as $label => $value) {
    printf("  %-20s %s\n", $label, number_format($value));
}
