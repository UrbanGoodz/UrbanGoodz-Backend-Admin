# P3A IMPORTER PARTIAL-IMPORT GUARD FIX REPORT
## Urban Goodz — Phase Data-Import P3A (Fix Path A)

**Marker (reused for the gated dry-run):**
`urban_goodz_session1_p3_staging_import_20260709_001`

**Status:** Guard fixed + final dry-run PASSES. Real import NOT run this phase
(per rules — requires explicit PM approval after reviewing this result).

---

## Root cause
`handle()` used an all-or-nothing guard:
```php
if ($report['failed'] > 0) {
    $this->error('Import blocked: validation failures remain. ...');
    return self::FAILURE;
}
// write transaction only reached if failed === 0
```
where `failed = count($excluded)`. The 44 excluded rows are **by-design
exclusions** (26 invalid source URLs + 18 duplicate candidates), NOT
resolvable data errors. PM approved importing the **431 valid candidates**
while excluding those 44, so the old guard could never pass.

## Files changed
- `app/Console/Commands/ImportUrbanGoodzCleanedBusinesses.php` (the importer command)

## Exact guard logic changed
1. Added a constant listing by-design (non-fatal) exclusion reasons:
   ```php
   private const NON_FATAL_EXCLUSION_REASONS = [
       'missing_or_invalid_source_url',
       'duplicate_candidate',
   ];
   ```
2. `validateRows()` now also counts fatal exclusions:
   ```php
   $fatalReasons = array_diff(array_unique($reasons), self::NON_FATAL_EXCLUSION_REASONS);
   if (!empty($fatalReasons)) { $fatalExcluded++; }
   ```
   and the report return adds:
   ```php
   'fatal' => $fatalExcluded,
   'excluded_non_fatal' => count($excluded) - $fatalExcluded,
   ```
3. `handle()` dry-run + real-import gates now key off `fatal`, not `failed`:
   ```php
   if ($dryRun) {
       $this->warn('DRY RUN ONLY: no records were written.');
       if ($report['fatal'] > 0) { /* warn, FAILURE */ }
       $this->info("Dry run ready: real import would stage N candidate row(s) and skip M by-design exclusion(s).");
       return self::SUCCESS;
   }
   if ($report['fatal'] > 0) {
       $this->error("Import blocked: N fatal validation failure(s) remain ...");
       return self::FAILURE;
   }
   ```
4. `printReport()` now prints `Fatal validation failures (blocks import)`
   and `By-design exclusions skipped (non-fatal)`.
5. The post-import success line reports staged + skipped counts.

## Why exclusions no longer block valid candidates
`valid_rows` (the 431) are rows with **zero** reasons — they were
never in `$excluded`. The by-design exclusions remain in `$excluded`
and are reported + skipped; they no longer flip `fatal`, so the real
import proceeds with only the valid candidates.

## What still blocks import as fatal
- `unresolved_category_or_module` > 0
- `unresolved_zone_id` / `zone_name_mismatch` > 0 (cannot stage safely)
- missing required fields on a candidate row
- invalid batch / unsafe options (still enforced first by `guardSafeOptions`)
- write/transaction failures

These all keep `fatal > 0` and block the import.

## Dry-run command used
```
php artisan urban-goodz:business-import-cleaned
  --zip=".../URBAN_GOODZ_ALL_ZONES_BUSINESS_ENRICHED_REVIEW_GATE_CLEANED.zip"
  --files=import_verified_ready.csv,import_partial_draft_review.csv
  --mode=draft --visibility=private --partnered=false
  --exclude-backlog=true --disable-age-restricted-fulfillment=true
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 --dry-run
```

## Dry-run counts after patch
- Candidate import count: **431**
- Excluded row count: **44**
- Fatal validation failures (blocks import): **0**
- By-design exclusions skipped (non-fatal): **44** (26 url + 18 dup)
- missing_or_invalid_source_url: **26**
- duplicate_candidate: **18**
- unresolved_category_or_module: **0**
- Zone valid / invalid / mismatched: 475 / 0 / 0
- Taxonomy mapped / held: 475 / 0
- category_ids resolved / pending: 52 / 423
- Age-restricted review-only: **86**
- Records written during dry-run: **0**
- Live stores/items/vendors created: **0**

Output includes: `Dry run ready: real import would stage 431 candidate
row(s) and skip 44 by-design exclusion(s).`

## Confirmation — no real import was run
This phase ran the dry-run only. The real import was NOT executed
(rule: do not run real import until PM explicitly approves after
reviewing P3A).

## Confirmation — 0 rows written
No `urban_goodz_sourced_businesses` / `urban_goodz_import_batches`
rows were created for the marker this phase.

## Confirmation — no stores/items/vendors/products created
Importer targets only the two sourcing tables. Nothing else was touched.

## Accepted P2A taxonomy behavior preserved
- No fake category_ids; `category_ids=[]` still allowed for pending rows.
- No `category_ids=[1]`; no categories/modules created.

## Approved safety settings preserved
`mode=draft`, `visibility=private`, `partnered=false`,
age-restricted rows `review_only`, no stores/items/vendors/products,
no `publishApprovedListings`.

## PM recommendation for next step
- **Approve running the real import** (remove `--dry-run`) using the same
  command + marker, now that the dry-run gate passes with fatal=0.
- Expected on real run: **431** `urban_goodz_sourced_businesses` rows +
  **1** `urban_goodz_import_batches` row; 44 by-design exclusions
  skipped; 0 stores/items/vendors/products.
- The 26 invalid-URL rows and 18 duplicate candidates (incl. the 4
  `db_store_*` live-store matches) remain excluded — never imported,
  no live stores merged/overwritten.
- Rehearse the rollback plan (P3_ROLLBACK_PLAN_...) before/after.
