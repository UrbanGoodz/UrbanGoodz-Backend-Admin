<?php
/**
 * PATH C â€” deterministic baseline migration ledger builder.
 *
 * Reads every repository migration, statically extracts its intended schema
 * effect, and verifies that effect against the LIVE Path C database (candidate
 * baseline already imported).
 *
 * It does NOT execute anything. It emits:
 *   - a per-migration ledger CSV with a verified verdict
 *   - the exact list of migrations to pre-record in `migrations` (represented)
 *   - the exact list to leave pending so `artisan migrate` runs only those
 *
 * Usage: php scripts/audit/pathc_ledger.php <db> [--write-ledger]
 */

$db   = $argv[1] ?? 'urbangoodz_pathc_20260723';
$write = in_array('--write-ledger', $argv, true);

$root = dirname(__DIR__, 2);
$migrationDir = $root . '/database/migrations';
$priorCsv     = $root . '/docs/audit/migration_classification.csv';

require_once __DIR__ . '/pathc_ledger_lib.php';

$pdo = pathc_pdo($db);

/* ---------------------------------------------------------------------------
 * Live schema snapshot
 * ------------------------------------------------------------------------- */
$tables = [];
$stmt = $pdo->prepare(
    "SELECT LOWER(table_name) FROM information_schema.tables
     WHERE table_schema = ? AND table_type = 'BASE TABLE'"
);
$stmt->execute([$db]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $tables[$t] = true;
}

$columns = [];
$stmt = $pdo->prepare(
    "SELECT LOWER(table_name), LOWER(column_name) FROM information_schema.columns
     WHERE table_schema = ?"
);
$stmt->execute([$db]);
foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$t, $c]) {
    $columns[$t][$c] = true;
}

$indexes = [];
$stmt = $pdo->prepare(
    "SELECT LOWER(table_name), LOWER(index_name) FROM information_schema.statistics
     WHERE table_schema = ?"
);
$stmt->execute([$db]);
foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$t, $i]) {
    $indexes[$t][$i] = true;
}

/* ---------------------------------------------------------------------------
 * Prior classification (preserved â€” this run verifies it, does not replace it)
 * ------------------------------------------------------------------------- */
$prior = [];
if (is_file($priorCsv)) {
    $fh = fopen($priorCsv, 'r');
    fgetcsv($fh);
    while ($row = fgetcsv($fh)) {
        if (count($row) < 2) {
            continue;
        }
        $prior[$row[0]] = $row[1];
    }
    fclose($fh);
}

/* ---------------------------------------------------------------------------
 * Walk every migration
 * ------------------------------------------------------------------------- */
$files = glob($migrationDir . '/*.php');
sort($files);

$ledger = [];
$counts = [
    'REPRESENTED'      => 0,
    'APPLICABLE'       => 0,
    'DUPLICATE'        => 0,
    'UNRESOLVED'       => 0,
    'NO-OP (DATA)'     => 0,
];

foreach ($files as $file) {
    $name = basename($file, '.php');
    $a    = analyse($file);

    $missing = [];
    $present = [];

    foreach ($a['creates'] as $t) {
        if (isset($tables[$t])) {
            $present[] = "table {$t}";
        } else {
            $missing[] = "table {$t}";
        }
    }
    foreach ($a['alters'] as $t => $cols) {
        if (!isset($tables[$t])) {
            // ALTER against a table the baseline does not have at all.
            $missing[] = "table {$t} (alter target)";
            continue;
        }
        foreach ($cols as $c) {
            if (isset($columns[$t][$c])) {
                $present[] = "{$t}.{$c}";
            } else {
                $missing[] = "{$t}.{$c}";
            }
        }
    }
    foreach ($a['dropColumns'] as $t => $cols) {
        foreach ($cols as $c) {
            // A drop is "already represented" when the column is ABSENT.
            if (!isset($columns[$t][$c])) {
                $present[] = "-{$t}.{$c} (already absent)";
            } else {
                $missing[] = "-{$t}.{$c} (still present)";
            }
        }
    }
    foreach ($a['drops'] as $t) {
        if (!isset($tables[$t])) {
            $present[] = "-table {$t} (already absent)";
        } else {
            $missing[] = "-table {$t} (still present)";
        }
    }

    /* --------- verdict ---------- */
    if (!$a['structural']) {
        $verdict = 'NO-OP (DATA)';
        $detail  = $a['dataOnly']
            ? 'no Schema:: operation; data/config only'
            : 'Schema:: present but no parsable structural operation (index/FK/raw only)';
        if ($a['rawSql']) {
            $detail .= '; contains raw SQL';
        }
    } elseif (!$missing) {
        $verdict = 'REPRESENTED';
        $detail  = 'every intended effect verified present: ' . implode(', ', array_slice($present, 0, 6))
                 . (count($present) > 6 ? ' â€¦(+' . (count($present) - 6) . ')' : '');
    } elseif (!$present) {
        $verdict = 'APPLICABLE';
        $detail  = 'no intended effect present: ' . implode(', ', array_slice($missing, 0, 6))
                 . (count($missing) > 6 ? ' â€¦(+' . (count($missing) - 6) . ')' : '');
    } else {
        // Partial: some effects present, some absent.
        $verdict = 'UNRESOLVED';
        $detail  = 'PARTIAL â€” present: ' . implode(', ', array_slice($present, 0, 4))
                 . ' | absent: ' . implode(', ', array_slice($missing, 0, 4));
    }

    if ($a['rawSql'] && $verdict === 'APPLICABLE') {
        $detail .= ' [raw SQL present â€” inspect before executing]';
    }

    $counts[$verdict]++;
    $ledger[] = [
        'migration'   => $name,
        'verdict'     => $verdict,
        'prior'       => $prior[$name] ?? '(none)',
        'creates'     => implode(' ', $a['creates']),
        'alters'      => implode(' ', array_keys($a['alters'])),
        'raw_sql'     => $a['rawSql'] ? 'yes' : 'no',
        'detail'      => $detail,
    ];
}

/* ---------------------------------------------------------------------------
 * Emit
 * ------------------------------------------------------------------------- */
if ($write) {
    $out = fopen($root . '/docs/audit/pathc_migration_ledger.csv', 'w');
    fputcsv($out, array_keys($ledger[0]));
    foreach ($ledger as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
}

echo "DATABASE: {$db}\n";
echo 'MIGRATION FILES: ' . count($files) . "\n\n";
foreach ($counts as $k => $v) {
    printf("%-16s %d\n", $k, $v);
}

echo "\n--- APPLICABLE (will be executed) ---\n";
foreach ($ledger as $r) {
    if ($r['verdict'] === 'APPLICABLE') {
        echo "  {$r['migration']}\n";
    }
}
echo "\n--- UNRESOLVED (partial â€” need individual review) ---\n";
foreach ($ledger as $r) {
    if ($r['verdict'] === 'UNRESOLVED') {
        echo "  {$r['migration']}\n      {$r['detail']}\n";
    }
}
echo "\n--- NO-OP / NOT STATICALLY DECIDABLE ---\n";
foreach ($ledger as $r) {
    if ($r['verdict'] === 'NO-OP (DATA)') {
        echo "  {$r['migration']}  [{$r['detail']}]\n";
    }
}
