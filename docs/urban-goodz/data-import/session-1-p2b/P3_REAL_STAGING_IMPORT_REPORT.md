# P3 REAL STAGING IMPORT REPORT
## Urban Goodz — Phase Data-Import P3 (PM-approved sourcing-table import)

**Marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Outcome:** REAL IMPORT WAS BLOCKED — **0 rows inserted.**

> Per the absolute rule, importer logic was NOT modified. The block was
> discovered and reported ("stop/report first"), not patched unilaterally.

---

## Command used (real, --dry-run removed)
```
php artisan urban-goodz:business-import-cleaned
  --zip=".../URBAN_GOODZ_ALL_ZONES_BUSINESS_ENRICHED_REVIEW_GATE_CLEANED.zip"
  --files=import_verified_ready.csv,import_partial_draft_review.csv
  --mode=draft --visibility=private --partnered=false
  --exclude-backlog=true --disable-age-restricted-fulfillment=true
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001
```

## Final dry-run counts (pre-import gate — all matched)
- Candidate import count: **431** ✅
- Excluded count: **44** ✅
- missing_or_invalid_source_url: **26** ✅
- duplicate_candidate: **18** ✅
- unresolved_category_or_module: **0** ✅
- Zone valid / invalid / mismatched: 475 / 0 / 0 ✅
- Taxonomy mapped / held: 475 / 0 ✅
- category_ids resolved / pending: 52 / 423 ✅
- Age-restricted review-only: 86 ✅
- Records written during dry-run: **0** ✅

## Real import result
- **Imported row count: 0** (blocked).
- Importer output: `Import blocked: validation failures remain. Re-run with --dry-run after resolving them.`
- Batch table rows: **0**.

## Root cause (BLOCKING BUG)
The importer's `handle()` enforces an **all-or-nothing** guard:
```php
if ($report['failed'] > 0) {
    $this->error('Import blocked: validation failures remain. ...');
    return self::FAILURE;
}
// ... write transaction only reached if failed === 0
```
where `$report['failed'] = count($excluded)`.

The 44 excluded rows are **by-design exclusions** (26 invalid source URLs
+ 18 duplicate candidates), NOT resolvable data errors. PM explicitly
approved importing the **431 valid candidates while excluding those 44**.
Because the guard treats every exclusion as a fatal "failure", the approved
partial import can never proceed as written.

## Verification of safety (post-block)
- `urban_goodz_sourced_businesses` rows for marker: **0**
- `urban_goodz_import_batches` rows for marker: **0**
- No stores / items / products / vendors created: **confirmed 0**
- `publishApprovedListings` not called; no activation: **confirmed**

## Age-restricted handling confirmation
Would have been enforced (`review_only` for 86 rows). Not exercised
because no rows were written.

## category_ids pending confirmation
Would have been `[]` for 384 candidate rows (423 all-row). Not
exercised — 0 rows written.

## Excluded invalid URL confirmation
26 rows correctly excluded from the (blocked) import — never imported.

## Excluded duplicate confirmation
18 duplicate candidates (incl. 4 `db_store_*` live-store matches)
correctly excluded — never imported; no live stores merged/overwritten.

## Confirmation no stores/items/vendors/products created
**Confirmed — 0.** Import aborted before any write.

## Confirmation no activation/publishing occurred
**Confirmed.** No activation, no `publishApprovedListings`, no deployment.

## Rollback plan path
`docs/urban-goodz/data-import/session-1-p2b/P3_ROLLBACK_PLAN_urban_goodz_session1_p3_staging_import_20260709_001.md`
(No-op: 0 rows exist for this marker. Do NOT execute unless PM instructs.)

## Remaining risks/blockers
1. **BLOCKER:** Importer all-or-nothing guard blocks the approved partial
   import. Must be resolved before the 431 candidates can be staged.
   Options (require PM decision):
   - **(A)** Modify `handle()` to import `valid_rows` even when
     `failed > 0`, logging skipped/excluded rows (true partial import).
   - **(B)** Pre-filter the two CSVs to drop the 44 excluded rows,
     yielding a 0-exclusion set so the guard passes unchanged.
2. 26 invalid URLs, 18 duplicates (4 live-store) remain excluded by design.
3. Module 14/15 taxonomy defect (separate ticket, non-blocking).

## PM recommendation
- **Do NOT re-run the real import yet.** It will block again identically.
- **Approve a fix path:** Option A (allow partial import in the importer,
  skipping excluded rows with a clear skip-report) is the cleanest and keeps
  the single source of truth (the ZIP). Option B requires producing filtered
  CSVs. Either needs explicit PM sign-off before any code change.
- Once the guard is adjusted per PM decision, re-run the dry-run gate, then
  the real import, then verify 431 sourced-business rows + 1 batch row,
  and rehearse the rollback plan.
- The 26 URL rows and 18 duplicates (incl. 4 store matches) stay excluded
  from this import regardless of the fix chosen.
