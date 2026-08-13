<?php
/**
 * PATH C — module migration reconciliation.
 *
 * `php artisan migrate` also runs migrations shipped inside each module's own
 * Database/Migrations directory. Those are outside the 337 repository migrations
 * but they still execute, so they need
 * the same represented/applicable treatment or the run will abort on "table exists".
 *
 * Usage: php scripts/audit/pathc_module_ledger.php <db> [--apply]
 */

$db    = $argv[1] ?? 'urbangoodz_pathc_20260723';
$apply = in_array('--apply', $argv, true);
$root  = dirname(__DIR__, 2);

require_once __DIR__ . '/pathc_ledger_lib.php';

$pdo = pathc_pdo($db);

$tables = [];
$st = $pdo->prepare("SELECT LOWER(table_name) FROM information_schema.tables WHERE table_schema=? AND table_type='BASE TABLE'");
$st->execute([$db]);
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $tables[$t] = true;
}

$columns = [];
$st = $pdo->prepare('SELECT LOWER(table_name), LOWER(column_name) FROM information_schema.columns WHERE table_schema=?');
$st->execute([$db]);
foreach ($st->fetchAll(PDO::FETCH_NUM) as [$t, $c]) {
    $columns[$t][$c] = true;
}

require_once __DIR__ . '/pathc_ledger_lib.php';

$files = [];
foreach (glob($root . '/Modules/*/Database/Migrations/*.php') as $f) {
    $files[basename($f, '.php')] = $f;
}
ksort($files);

$record = [];
$exec   = [];

foreach ($files as $name => $file) {
    $a = analyse($file);

    $missing = $present = [];
    foreach ($a['creates'] as $t) {
        isset($tables[$t]) ? $present[] = "table {$t}" : $missing[] = "table {$t}";
    }
    foreach ($a['alters'] as $t => $cols) {
        if (!isset($tables[$t])) {
            $missing[] = "table {$t} (alter target)";
            continue;
        }
        foreach ($cols as $c) {
            isset($columns[$t][$c]) ? $present[] = "{$t}.{$c}" : $missing[] = "{$t}.{$c}";
        }
    }

    if (!$a['structural']) {
        $exec[$name] = 'no parsable structural op — not schema-observable; left pending';
    } elseif (!$missing) {
        $record[$name] = 'verified present: ' . implode(', ', array_slice($present, 0, 5));
    } elseif (!$present) {
        $exec[$name] = 'absent: ' . implode(', ', array_slice($missing, 0, 5));
    } else {
        $record[$name] = 'PARTIAL — present: ' . implode(', ', array_slice($present, 0, 3))
                       . ' | absent: ' . implode(', ', array_slice($missing, 0, 3));
    }
}

printf("MODULE MIGRATIONS  : %d\n", count($files));
printf("RECORD REPRESENTED : %d\n", count($record));
printf("LEAVE PENDING      : %d\n\n", count($exec));

foreach ($record as $n => $w) {
    echo "  [REPRESENTED] {$n}\n      {$w}\n";
}
foreach ($exec as $n => $w) {
    echo "  [PENDING]     {$n}\n      {$w}\n";
}

if (!$apply) {
    echo "\n(dry run — pass --apply)\n";
    exit(0);
}

$ins = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)');
$has = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
$n = 0;
foreach (array_keys($record) as $name) {
    $has->execute([$name]);
    if (!$has->fetchColumn()) {
        $ins->execute([$name]);
        $n++;
    }
}
echo "\nRECORDED {$n} module migrations as batch 1\n";
