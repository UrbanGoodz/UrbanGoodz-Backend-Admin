<?php
/**
 * Shared static-analysis helpers for the Path C migration ledger.
 *
 * Extracted so both the repository ledger (pathc_ledger.php) and the module
 * ledger (pathc_module_ledger.php) classify migrations by identical rules.
 */

// Column-defining Blueprint methods whose FIRST string arg is a column name.
const COLUMN_METHODS = [
    'string', 'char', 'text', 'tinyText', 'mediumText', 'longText',
    'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
    'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger',
    'unsignedMediumInteger', 'unsignedBigInteger',
    'increments', 'bigIncrements', 'float', 'double', 'decimal',
    'unsignedDecimal', 'boolean', 'enum', 'set', 'json', 'jsonb',
    'date', 'dateTime', 'dateTimeTz', 'time', 'timeTz', 'timestamp',
    'timestampTz', 'year', 'binary', 'uuid', 'ulid', 'foreignId',
    'foreignUuid', 'ipAddress', 'macAddress', 'geometry', 'point',
];

/**
 * Return the body of the named method by brace-matching from its signature.
 * Migrations declare both up() and down(); analysing the whole file counts
 * down()'s dropIfExists as forward intent, which is wrong.
 */
function methodBody(string $src, string $method): ?string
{
    if (!preg_match('~function\s+' . preg_quote($method, '~') . '\s*\([^)]*\)[^{]*\{~', $src, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $i     = $m[0][1] + strlen($m[0][0]); // just past the opening brace
    $depth = 1;
    $len   = strlen($src);
    $start = $i;

    for (; $i < $len && $depth > 0; $i++) {
        $ch = $src[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
        } elseif ($ch === "'" || $ch === '"') {
            // skip string literals so braces inside them do not shift depth
            $q = $ch;
            for ($i++; $i < $len; $i++) {
                if ($src[$i] === '\\') {
                    $i++;
                    continue;
                }
                if ($src[$i] === $q) {
                    break;
                }
            }
        }
    }

    return substr($src, $start, $i - $start - 1);
}

function analyse(string $path): array
{
    $src  = file_get_contents($path);
    $name = basename($path, '.php');

    $out = [
        'creates'      => [],   // tables created
        'drops'        => [],   // tables dropped
        'alters'       => [],   // table => [columns added]
        'dropColumns'  => [],   // table => [columns dropped]
        'renames'      => [],
        'rawSql'       => false,
        'dataOnly'     => false,
        'structural'   => false,
        'noUpMethod'   => false,
    ];

    // Strip comments so commented-out code is never counted as intent.
    $clean = preg_replace('~/\*.*?\*/~s', '', $src);
    $clean = preg_replace('~^\s*//.*$~m', '', $clean);

    // Forward intent lives in up() only. down() is the inverse and must not
    // be read as intent (its dropIfExists would negate every create).
    $up = methodBody($clean, 'up');
    if ($up === null) {
        $out['noUpMethod'] = true;
    } else {
        $clean = $up;
    }

    if (preg_match('~DB::(statement|unprepared|raw)\s*\(~i', $clean)) {
        $out['rawSql'] = true;
    }

    // Schema::create('x', ...) and Schema::createIfNotExists
    if (preg_match_all("~Schema::(?:connection\([^)]*\)->)?create(?:IfNotExists)?\s*\(\s*['\"]([^'\"]+)['\"]~", $clean, $m)) {
        $out['creates'] = array_unique(array_map('strtolower', $m[1]));
    }

    // Schema::dropIfExists('x') / Schema::drop('x')
    if (preg_match_all("~Schema::(?:connection\([^)]*\)->)?drop(?:IfExists)?\s*\(\s*['\"]([^'\"]+)['\"]~", $clean, $m)) {
        $out['drops'] = array_unique(array_map('strtolower', $m[1]));
    }

    if (preg_match_all("~Schema::(?:connection\([^)]*\)->)?rename\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]+)['\"]~", $clean, $m)) {
        foreach ($m[1] as $i => $from) {
            $out['renames'][] = [strtolower($from), strtolower($m[2][$i])];
        }
    }

    /*
     * Schema::table('x', function (Blueprint $t) { ... })  â€” attribute the
     * column operations inside each closure to that closure's own table by
     * slicing the source between successive Schema:: calls. This is what fixes
     * the prior classifier's "first target table only" limitation.
     */
    $offsets = [];
    if (preg_match_all(
        "~Schema::(?:connection\([^)]*\)->)?(table|create|createIfNotExists)\s*\(\s*['\"]([^'\"]+)['\"]~",
        $clean, $m, PREG_OFFSET_CAPTURE
    )) {
        foreach ($m[0] as $i => $hit) {
            $offsets[] = [
                'pos'   => $hit[1],
                'kind'  => $m[1][$i][0],
                'table' => strtolower($m[2][$i][0]),
            ];
        }
    }

    foreach ($offsets as $i => $blk) {
        $start = $blk['pos'];
        $end   = $offsets[$i + 1]['pos'] ?? strlen($clean);
        $body  = substr($clean, $start, $end - $start);
        $tbl   = $blk['table'];

        // Only ALTERs need column-level verification; creates are verified by
        // table existence.
        if ($blk['kind'] !== 'table') {
            continue;
        }

        $methods = implode('|', COLUMN_METHODS);
        if (preg_match_all("~->\s*({$methods})\s*\(\s*['\"]([^'\"]+)['\"]~", $body, $cm)) {
            foreach ($cm[2] as $col) {
                $out['alters'][$tbl][] = strtolower($col);
            }
        }
        // ->timestamps() / ->softDeletes() add known column names
        if (preg_match('~->\s*timestamps\s*\(~', $body)) {
            $out['alters'][$tbl][] = 'created_at';
            $out['alters'][$tbl][] = 'updated_at';
        }
        if (preg_match('~->\s*softDeletes\s*\(~', $body)) {
            $out['alters'][$tbl][] = 'deleted_at';
        }

        if (preg_match_all("~->\s*dropColumn\s*\(\s*['\"]([^'\"]+)['\"]~", $body, $dm)) {
            foreach ($dm[1] as $col) {
                $out['dropColumns'][$tbl][] = strtolower($col);
            }
        }
        if (preg_match_all("~->\s*dropColumn\s*\(\s*\[([^\]]+)\]~", $body, $dm)) {
            foreach ($dm[1] as $list) {
                if (preg_match_all("~['\"]([^'\"]+)['\"]~", $list, $lm)) {
                    foreach ($lm[1] as $col) {
                        $out['dropColumns'][$tbl][] = strtolower($col);
                    }
                }
            }
        }
    }

    foreach ($out['alters'] as $t => $cols) {
        $out['alters'][$t] = array_values(array_unique($cols));
    }

    $out['structural'] = $out['creates'] || $out['drops'] || $out['alters']
        || $out['dropColumns'] || $out['renames'];

    // A migration with no Schema:: work at all is data/config only.
    $out['dataOnly'] = !$out['structural'] && !preg_match('~Schema::~', $clean);

    return $out;
}


/**
 * Open the Path C audit connection.
 *
 * Credentials come from the environment so no login lands in Git. Export
 * PATHC_DB_USER / PATHC_DB_PASS (and optionally PATHC_DB_HOST, PATHC_DB_PORT)
 * before running any ledger script.
 */
function pathc_pdo(string $db): PDO
{
    $host = getenv('PATHC_DB_HOST') ?: '127.0.0.1';
    $port = getenv('PATHC_DB_PORT') ?: '3306';
    $user = getenv('PATHC_DB_USER');
    $pass = getenv('PATHC_DB_PASS');

    if ($user === false || $user === '') {
        fwrite(STDERR, "PATHC_DB_USER is not set; refusing to guess a database login.\n");
        exit(2);
    }

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass === false ? '' : $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}
