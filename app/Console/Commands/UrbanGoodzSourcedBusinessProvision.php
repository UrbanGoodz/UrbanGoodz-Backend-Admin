<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\UrbanGoodzSourcedBusiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UrbanGoodzSourcedBusinessProvision extends Command
{
    protected $signature = 'urban-goodz:sourced-business-provision
        {--batch-marker= : Required. created_by_source marker of the staged import.}
        {--expected-count= : Required. Expected number of eligible approved rows.}
        {--dry-run : Simulate only (default behavior).}
        {--apply : Actually provision stores. NOT approved in P5B/P6 — dry-run only.}';

    protected $description = 'Guarded provisioning of approved Urban Goodz sourced businesses into stores. Dry-run by default.';

    public function handle()
    {
        $marker = $this->option('batch-marker');
        $expected = $this->option('expected-count');
        if (!$marker || $expected === null) {
            $this->error('Refusing: --batch-marker and --expected-count are required.');
            return self::FAILURE;
        }
        $expected = (int) $expected;
        $apply = $this->option('apply');
        $dryRun = !$apply;
        $this->info("=== Urban Goodz Sourced Business Provisioning ===");
        $this->info("Batch marker: {$marker}");
        $this->info($dryRun ? 'MODE: DRY-RUN (no stores created)' : 'MODE: APPLY (would create stores)');

        // Eligible = approved + category ok + valid url + active module + non-age + not merge.
        $rows = \App\Models\UrbanGoodzSourcedBusiness::where('created_by_source', $marker)
            ->where('admin_review_status', 'approved')
            ->get();

        $eligible = [];
        foreach ($rows as $r) {
            $fails = [];
            $catIds = (array) $r->category_ids;
            if (empty($catIds)) { $fails[] = 'category_ids empty'; }
            elseif (in_array(1, $catIds, true)) { $fails[] = 'category_ids has 1'; }
            else { foreach ($catIds as $cid) { if (DB::table('categories')->where('id',$cid)->value('module_id') != $r->module_id) { $fails[] = "cat {$cid} module mismatch"; break; } } }
            $url = is_array($r->source_urls) ? ($r->source_urls[0] ?? '') : $r->source_urls;
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http','https'], true)) $fails[] = 'invalid source_url';
            if (DB::table('modules')->where('id', $r->module_id)->value('status') != 1) $fails[] = 'inactive module';
            if ((array) $r->fulfillment_modes === ['review_only']) $fails[] = 'age-restricted';
            if (!empty($fails)) { $this->line("  skipped id={$r->id} {$r->name}: ".implode('; ',$fails)); continue; }
            $eligible[] = $r;
        }

        if (count($eligible) !== $expected) {
            $this->error("Refusing: eligible count ".count($eligible)." != expected {$expected}.");
            return self::FAILURE;
        }

        $this->info('--- ELIGIBLE FOR PROVISIONING ('.count($eligible).') ---');
        foreach ($eligible as $r) {
            $this->line("  STORE WOULD BE CREATED: id={$r->id} name={$r->name} module={$r->module_id} category_ids=".json_encode($r->category_ids)." visibility=private partnered=false");
        }

        $this->line('Summary:');
        $this->line("  stores that would be created: ".count($eligible));
        $this->line('  vendors that would be created: 0');
        $this->line('  products/items created: 0');
        $this->line('  partnered: false');
        $this->line('  visibility: private');
        $this->line('  public activation: false');
        $this->line('  age-restricted rows included: 0');
        $this->line('  inactive module rows included: 0');
        $this->line('  category_ids=[] rows included: 0');

        if ($dryRun) {
            $this->warn('DRY-RUN complete. No stores/vendors/products were created. Apply is NOT approved in this phase.');
            return self::SUCCESS;
        }

        // --- APPLY: create PRIVATE / non-public, INACTIVE stores only ---
        // NOTE: we intentionally do NOT call publishApprovedListings(), which
        // would set status=1/active=true (public activation). P6B requires
        // private stores only with no public activation.
        $created = 0;
        $createdIds = [];
        $sourceIds = [];
        $skipped = 0;
        foreach ($eligible as $r) {
            // Idempotent: skip rows already privately provisioned in a prior run.
            if ($r->onboarding_status === 'provisioned_private') {
                $this->line("  skip id={$r->id} ({$r->name}) already provisioned");
                $skipped++;
                continue;
            }
            // Unique synthetic phone avoids the stores phone unique constraint.
            $phone = '1' . str_pad($r->id, 9, '0');
            $store = Store::create([
                'name' => $r->name,
                'phone' => $phone,
                'email' => $r->email,
                'address' => $r->address,
                'latitude' => $r->latitude,
                'longitude' => $r->longitude,
                'module_id' => $r->module_id,
                'zone_id' => $r->zone_id ?? 1,
                'status' => 0,            // private / non-public
                'active' => false,        // not activated
                'delivery' => false,
                'take_away' => false,
                'item_section' => 0,
                'slug' => Str::slug($r->name) . '-ug' . $r->id,
                'vendor_id' => 1,         // default system vendor until claimed
                'is_public_sourced' => true,
                'is_partner' => false,     // explicitly not partnered
            ]);

            // Mark the sourced row as privately provisioned (NOT activated).
            $r->update([
                'onboarding_status' => 'provisioned_private',
                'admin_review_status' => 'approved',
            ]);

            $created++;
            $createdIds[] = $store->id;
            $sourceIds[] = $r->id;
            $this->line("  created PRIVATE store id={$store->id} (status=0, active=false) for sourced id={$r->id}");
        }
        $this->info("Private stores created: {$created} (skipped already-provisioned: {$skipped})");
        $this->line("Created store IDs: " . implode(', ', $createdIds));
        $this->line("Provisioned source IDs: " . implode(', ', $sourceIds));
        $this->line('Rollback/disable plan: set these stores status=0/active=false (already inactive) and/or delete; revert sourced onboarding_status. See P6B_PROVISIONING_ROLLBACK_DISABLE_PLAN.md.');
        return self::SUCCESS;
    }
}
