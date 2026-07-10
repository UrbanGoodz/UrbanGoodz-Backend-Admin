<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UrbanGoodzSourcedBusinessFasttrackApprove extends Command
{
    protected $signature = 'urban-goodz:sourced-business-fasttrack-approve
        {--batch-marker= : Required. created_by_source marker of the staged import.}
        {--csv= : Required. Path to the P5A fast-track CSV.}
        {--dry-run : Simulate only (default behavior).}
        {--apply : Perform the approval update. Required for any change.}';

    protected $description = 'Approve exactly the 32 P5A fast-track sourced-business rows (review status only). Dry-run by default.';

    private const EXPECTED_COUNT = 32;

    private function norm($s) { return preg_replace('/[^a-z0-9]/', '', strtolower((string) $s)); }

    public function handle()
    {
        $marker = $this->option('batch-marker');
        $csvPath = $this->option('csv');
        if (!$marker || !$csvPath) {
            $this->error('Refusing: --batch-marker and --csv are required.');
            return self::FAILURE;
        }
        $apply = $this->option('apply');
        $dryRun = !$apply;
        $this->info("=== Urban Goodz Fast-Track Approval ===");
        $this->info("Batch marker: {$marker}");
        $this->info($dryRun ? 'MODE: DRY-RUN (no changes)' : 'MODE: APPLY (writing approvals)');

        $full = file_exists($csvPath) ? $csvPath : base_path($csvPath);
        if (!file_exists($full)) { $this->error("Refusing: CSV not found at {$csvPath}"); return self::FAILURE; }

        $ids = [];
        if (($fh = fopen($full, 'r')) !== false) {
            $header = fgetcsv($fh);
            while (($row = fgetcsv($fh)) !== false) {
                if (empty($row)) continue;
                $ids[] = (int) $row[0];
            }
            fclose($fh);
        }
        $ids = array_values(array_filter($ids));
        if (count($ids) !== self::EXPECTED_COUNT) {
            $this->error("Refusing: CSV must contain exactly ".self::EXPECTED_COUNT." rows, found ".count($ids).".");
            return self::FAILURE;
        }

        // Per-row eligibility guards.
        $eligible = [];
        foreach ($ids as $id) {
            $r = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->find($id);
            if (!$r) { $this->error("Refusing: id {$id} not found in batch marker."); return self::FAILURE; }
            $fails = [];
            if ($r->admin_review_status !== 'pending') $fails[] = 'status='.$r->admin_review_status;
            $catIds = (array) $r->category_ids;
            if (empty($catIds)) $fails[] = 'category_ids empty';
            elseif (in_array(1, $catIds, true)) $fails[] = 'category_ids has 1';
            else { foreach ($catIds as $cid) { if (DB::table('categories')->where('id',$cid)->value('module_id') != $r->module_id) { $fails[] = "cat {$cid} module mismatch"; break; } } }
            $url = is_array($r->source_urls) ? ($r->source_urls[0] ?? '') : $r->source_urls;
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http','https'], true)) $fails[] = 'invalid source_url';
            if (DB::table('modules')->where('id', $r->module_id)->value('status') != 1) $fails[] = 'inactive module';
            if ((array) $r->fulfillment_modes === ['review_only']) $fails[] = 'age-restricted';
            if (in_array('partnered_status_true', (array) $r->tags, true)) $fails[] = 'partnered';
            if (!empty($fails)) { $this->error("Refusing: id {$id} ({$r->name}) fails: ".implode('; ',$fails)); return self::FAILURE; }
            $eligible[] = $r;
        }

        if (count($eligible) !== self::EXPECTED_COUNT) {
            $this->error("Refusing: eligible count ".count($eligible)." != expected ".self::EXPECTED_COUNT.".");
            return self::FAILURE;
        }

        $this->info("--- ELIGIBLE (".count($eligible).") ---");
        foreach ($eligible as $r) { $this->line("  id={$r->id} {$r->name} module={$r->module_id} cat=".json_encode($r->category_ids)); }

        $beforeApproved = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->where('admin_review_status','approved')->count();
        $beforePending = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->where('admin_review_status','pending')->count();

        if ($dryRun) {
            $this->warn('DRY-RUN complete. Re-run with --apply to write approvals.');
            $this->line("After (predicted): approved=".(self::EXPECTED_COUNT).", pending=".($beforePending - self::EXPECTED_COUNT));
            $idList = implode(', ', $ids);
            $this->line('Rollback SQL:');
            $this->line("  UPDATE urban_goodz_sourced_businesses SET admin_review_status = 'pending' WHERE id IN ({$idList}) AND created_by_source = '{$marker}' AND admin_review_status = 'approved';");
            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($eligible as $r) {
            $updated += $r->update(['admin_review_status' => 'approved']) ? 1 : 0;
        }
        $afterApproved = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->where('admin_review_status','approved')->count();
        $afterPending = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->where('admin_review_status','pending')->count();

        $this->info("--- AFTER ---");
        $this->info("Approvals written: {$updated} (expected ".self::EXPECTED_COUNT.")");
        $this->line("approved={$afterApproved} pending={$afterPending}");
        $this->line('Rollback SQL:');
        $idList = implode(', ', $ids);
        $this->line("  UPDATE urban_goodz_sourced_businesses SET admin_review_status = 'pending' WHERE id IN ({$idList}) AND created_by_source = '{$marker}' AND admin_review_status = 'approved';");

        if ($updated !== self::EXPECTED_COUNT || $afterApproved !== self::EXPECTED_COUNT || $afterPending !== ($beforePending - self::EXPECTED_COUNT)) {
            $this->error('Mismatch detected. Verify before relying on this approval.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
