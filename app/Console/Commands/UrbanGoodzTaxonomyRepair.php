<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UrbanGoodzTaxonomyRepair extends Command
{
    protected $signature = 'urban-goodz:taxonomy-repair
        {--dry-run : Simulate only (default behavior).}
        {--apply : Perform the taxonomy update. Required for any change.}';

    protected $description = 'Repair Urban Goodz taxonomy: reassign beauty categories 820-839 from Retail/Shopping (module 13) to Beauty/Personal Care (module 14). Dry-run by default.';

    // Confirmed, safe, exact scope only.
    private const SOURCE_MODULE_ID = 13; // Retail/Shopping
    private const TARGET_MODULE_ID = 14; // Beauty/Personal Care
    private const CATEGORY_MIN = 820;
    private const CATEGORY_MAX = 839;
    private const EXPECTED_COUNT = 20;

    public function handle()
    {
        $apply = $this->option('apply');
        $dryRun = !$apply; // default is dry-run

        $this->info('=== Urban Goodz Taxonomy Repair ===');
        $this->info($dryRun ? 'MODE: DRY-RUN (no changes will be written)' : 'MODE: APPLY (writing changes)');

        // --- Preconditions: refuse if expected modules are missing ---
        $srcMod = DB::table('modules')->where('id', self::SOURCE_MODULE_ID)->value('module_name');
        $tgtMod = DB::table('modules')->where('id', self::TARGET_MODULE_ID)->value('module_name');
        if (!$srcMod || !$tgtMod) {
            $this->error("Refusing: expected modules 13 ('Retail/Shopping') and 14 ('Beauty/Personal Care') must both exist. Found: 13=".var_export($srcMod,true)." 14=".var_export($tgtMod,true));
            return self::FAILURE;
        }
        $this->line("Source module: 13 = {$srcMod}");
        $this->line("Target module: 14 = {$tgtMod}");

        // --- Preconditions: refuse if category count does not match expected ---
        $affected = DB::table('categories')
            ->whereBetween('id', [self::CATEGORY_MIN, self::CATEGORY_MAX])
            ->where('module_id', self::SOURCE_MODULE_ID)
            ->orderBy('id')
            ->get(['id', 'name', 'module_id', 'parent_id']);

        if ($affected->count() !== self::EXPECTED_COUNT) {
            $this->error("Refusing: expected exactly ".self::EXPECTED_COUNT." categories in range ".self::CATEGORY_MIN."-".self::CATEGORY_MAX." under module 13, found {$affected->count()}.");
            return self::FAILURE;
        }

        // --- Preconditions: refuse if any affected category is referenced live ---
        $liveImpact = 0;
        foreach (['items'] as $t) {
            if (!\Schema::hasColumn($t, 'category_ids')) continue;
            foreach (DB::table($t)->whereNotNull('category_ids')->cursor() as $row) {
                $dec = json_decode($row->category_ids, true);
                $arr = is_array($dec) ? $dec : [];
                if (array_intersect(array_map('intval', $arr), range(self::CATEGORY_MIN, self::CATEGORY_MAX))) { $liveImpact++; }
            }
        }
        if ($liveImpact > 0) {
            $this->error("Refusing: {$liveImpact} live items reference categories ".self::CATEGORY_MIN."-".self::CATEGORY_MAX.". Manual review required before repair.");
            return self::FAILURE;
        }

        $beforeSrc = DB::table('categories')->where('module_id', self::SOURCE_MODULE_ID)->count();
        $beforeTgt = DB::table('categories')->where('module_id', self::TARGET_MODULE_ID)->count();

        $this->info('--- BEFORE ---');
        $this->line("Module 13 ({$srcMod}) categories: {$beforeSrc}");
        $this->line("Module 14 ({$tgtMod}) categories: {$beforeTgt}");
        $this->line("Categories to move (".self::CATEGORY_MIN."-".self::CATEGORY_MAX."):");
        foreach ($affected as $c) {
            $this->line("  id={$c->id} name={$c->name} (parent={$c->parent_id})");
        }

        if ($dryRun) {
            $this->warn('DRY-RUN complete. Re-run with --apply to write changes.');
            $this->line("After (predicted): module 13 = ".($beforeSrc - self::EXPECTED_COUNT).", module 14 = ".($beforeTgt + self::EXPECTED_COUNT));
            $this->line('Rollback SQL:');
            $this->line("  UPDATE categories SET module_id = ".self::SOURCE_MODULE_ID." WHERE id BETWEEN ".self::CATEGORY_MIN." AND ".self::CATEGORY_MAX." AND module_id = ".self::TARGET_MODULE_ID.";");
            return self::SUCCESS;
        }

        // --- APPLY: update only; never delete ---
        $moved = DB::table('categories')
            ->whereBetween('id', [self::CATEGORY_MIN, self::CATEGORY_MAX])
            ->where('module_id', self::SOURCE_MODULE_ID)
            ->update(['module_id' => self::TARGET_MODULE_ID]);

        $afterSrc = DB::table('categories')->where('module_id', self::SOURCE_MODULE_ID)->count();
        $afterTgt = DB::table('categories')->where('module_id', self::TARGET_MODULE_ID)->count();

        $this->info('--- AFTER ---');
        $this->line("Module 13 ({$srcMod}) categories: {$afterSrc}");
        $this->line("Module 14 ({$tgtMod}) categories: {$afterTgt}");
        $this->info("Moved: {$moved} (expected ".self::EXPECTED_COUNT.")");

        if ($moved !== self::EXPECTED_COUNT) {
            $this->error("Mismatch: moved {$moved} != expected ".self::EXPECTED_COUNT.". Investigate before relying on this repair.");
            return self::FAILURE;
        }

        $this->info('Repair applied. Rollback SQL:');
        $this->line("  UPDATE categories SET module_id = ".self::SOURCE_MODULE_ID." WHERE id BETWEEN ".self::CATEGORY_MIN." AND ".self::CATEGORY_MAX." AND module_id = ".self::TARGET_MODULE_ID.";");
        return self::SUCCESS;
    }
}
