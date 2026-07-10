<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UrbanGoodzSourcedBusinessCategoryBackfill extends Command
{
    protected $signature = 'urban-goodz:sourced-business-category-backfill
        {--batch-marker= : Required. created_by_source marker of the staged import.}
        {--dry-run : Simulate only (default behavior).}
        {--apply : Perform the backfill update. Required for any change.}';

    protected $description = 'Backfill category_ids for Urban Goodz staged sourced businesses using EXACT subcategory->category matches only. Dry-run by default.';

    private const EXPECTED_MATCHES = 11;

    private function norm($s)
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
    }

    public function handle()
    {
        $marker = $this->option('batch-marker');
        if (!$marker) {
            $this->error('Refusing: --batch-marker is required.');
            return self::FAILURE;
        }
        $apply = $this->option('apply');
        $dryRun = !$apply;
        $this->info("=== Urban Goodz Sourced Business Category Backfill ===");
        $this->info("Batch marker: {$marker}");
        $this->info($dryRun ? 'MODE: DRY-RUN (no changes will be written)' : 'MODE: APPLY (writing changes)');

        // Load categories by module (id => [slug, name]).
        $catsByModule = [];
        foreach (DB::table('categories')->get(['id', 'name', 'slug', 'module_id']) as $c) {
            $catsByModule[$c->module_id][$c->id] = [
                'slug' => $this->norm($c->slug),
                'name' => $this->norm($c->name),
            ];
        }

        // Candidate set: pending rows (empty category_ids) for the marker.
        $pendingBefore = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)
            ->whereRaw('JSON_LENGTH(category_ids) = 0')
            ->count();

        $rows = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)
            ->whereRaw('JSON_LENGTH(category_ids) = 0')
            ->get();

        $matches = [];
        foreach ($rows as $r) {
            $tags = (array) $r->tags;
            $subcat = $tags[1] ?? null;
            if (!$subcat || !isset($catsByModule[$r->module_id])) {
                continue;
            }
            foreach ($catsByModule[$r->module_id] as $cid => $c) {
                if ($c['slug'] === $this->norm($subcat) || $c['name'] === $this->norm($subcat)) {
                    $matches[] = ['row' => $r, 'cat_id' => $cid, 'subcat' => $subcat];
                    break;
                }
            }
        }

        // --- Refuse if match count is not exactly the expected 11 ---
        if (count($matches) !== self::EXPECTED_MATCHES) {
            $this->error("Refusing: expected exactly ".self::EXPECTED_MATCHES." exact matches, found ".count($matches).".");
            return self::FAILURE;
        }

        // --- Per-match guards ---
        foreach ($matches as $m) {
            $r = $m['row'];
            $cat = DB::table('categories')->where('id', $m['cat_id'])->first();
            if (!$cat) { $this->error("Refusing: category id {$m['cat_id']} missing."); return self::FAILURE; }
            if ((int) $cat->module_id !== (int) $r->module_id) {
                $this->error("Refusing: row {$r->id} module {$r->module_id} != category {$m['cat_id']} module {$cat->module_id}.");
                return self::FAILURE;
            }
            if ($m['cat_id'] === 1) { $this->error("Refusing: category id 1 is not allowed."); return self::FAILURE; }
            if (!empty((array) $r->category_ids)) { $this->error("Refusing: row {$r->id} already has category_ids."); return self::FAILURE; }
            if ($r->created_by_source !== $marker) { $this->error("Refusing: row {$r->id} outside batch marker."); return self::FAILURE; }
        }

        $this->info('--- EXACT MATCHES ('.count($matches).') ---');
        foreach ($matches as $m) {
            $r = $m['row'];
            $this->line("  id={$r->id} name={$r->name} module={$r->module_id} subcat={$m['subcat']} -> category {$m['cat_id']}");
        }
        $this->line("Pending category_ids before: {$pendingBefore}");
        $this->line("Manual-review rows remaining (expected): ".($pendingBefore - self::EXPECTED_MATCHES));

        if ($dryRun) {
            $this->warn('DRY-RUN complete. Re-run with --apply to write changes.');
            $this->line('Rollback SQL:');
            $ids = implode(', ', array_map(fn ($m) => $m['row']->id, $matches));
            $this->line("  UPDATE urban_goodz_sourced_businesses SET category_ids = '[]' WHERE id IN ({$ids}) AND created_by_source = '{$marker}';");
            return self::SUCCESS;
        }

        // --- APPLY: update only the 11 rows ---
        $updated = 0;
        foreach ($matches as $m) {
            $ok = DB::table('urban_goodz_sourced_businesses')
                ->where('id', $m['row']->id)
                ->where('created_by_source', $marker)
                ->update(['category_ids' => json_encode([$m['cat_id']])]);
            $updated += $ok;
        }

        $pendingAfter = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)
            ->whereRaw('JSON_LENGTH(category_ids) = 0')
            ->count();

        $this->info("--- AFTER ---");
        $this->line("Rows updated: {$updated} (expected ".self::EXPECTED_MATCHES.")");
        $this->line("Pending category_ids after: {$pendingAfter}");
        $this->info('Rollback SQL:');
        $ids = implode(', ', array_map(fn ($m) => $m['row']->id, $matches));
        $this->line("  UPDATE urban_goodz_sourced_businesses SET category_ids = '[]' WHERE id IN ({$ids}) AND created_by_source = '{$marker}';");

        if ($updated !== self::EXPECTED_MATCHES || $pendingAfter !== ($pendingBefore - self::EXPECTED_MATCHES)) {
            $this->error('Mismatch detected. Verify before relying on this backfill.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
