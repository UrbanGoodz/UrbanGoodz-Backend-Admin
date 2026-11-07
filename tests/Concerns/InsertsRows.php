<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insert a row into any table, filling every NOT NULL column that has no default
 * from the live schema. Keeps authorization fixtures independent of unrelated
 * columns. Use with DatabaseTransactions and FOREIGN_KEY_CHECKS=0.
 */
trait InsertsRows
{
    protected function insertRow(string $table, array $values): int
    {
        $row = [];
        foreach (Schema::getColumns($table) as $col) {
            $name = $col['name'];
            if (array_key_exists($name, $values)) {
                $row[$name] = $values[$name];
                continue;
            }
            if ($col['nullable'] || $col['default'] !== null || $col['auto_increment']) {
                continue;
            }
            $type = strtolower($col['type_name']);
            $row[$name] = match (true) {
                str_contains($type, 'int'), in_array($type, ['decimal', 'float', 'double'], true) => 0,
                $type === 'date' => now()->toDateString(),
                in_array($type, ['datetime', 'timestamp'], true) => now()->toDateTimeString(),
                $type === 'time' => '10:00:00',
                $type === 'json' => '[]',
                default => 'x',
            };
        }
        if (Schema::hasColumn($table, 'created_at') && ! isset($row['created_at'])) {
            $row['created_at'] = $row['updated_at'] = now();
        }

        return (int) DB::table($table)->insertGetId($row);
    }
}
