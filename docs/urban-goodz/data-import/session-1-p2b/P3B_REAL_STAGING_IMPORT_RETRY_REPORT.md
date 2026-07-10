# P3B REAL STAGING IMPORT RETRY REPORT
## Urban Goodz — Phase Data-Import P3B (PM-approved sourcing-table import, real run)

**Marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Outcome:** REAL IMPORT SUCCEEDED — **431 rows inserted.**

> P3A guard fix (Fix Path A) was accepted. This phase ran the real
> import (--dry-run removed) with the same approved marker.

---

## P3A guard fix accepted
Importer now gates on **fatal** errors only; by-design exclusions
(26 invalid URLs + 18 duplicates) are skipped, not blocking.

## Command used (real, --dry-run removed)
```
php artisan urban-goodz:business-import-cleaned
  --zip=".../URBAN_GOODZ_ALL_ZONES_BUSINESS_ENRICHED_REVIEW_GATE_CLEANED.zip"
  --files=import_verified_ready.csv,import_partial_draft_review.csv
  --mode=draft --visibility=private --partnered=false
  --exclude-backlog=true --disable-age-restricted-fulfillment=true
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001
```

## Batch marker
`urban_goodz_session1_p3_staging_import_20260709_001`

## Final dry-run counts (P3A, pre-import gate — matched)
431 candidates · 44 excluded (26 url + 18 dup) · fatal=0 · 0 unresolved
· 475 zones valid · 86 age-restricted · 0 written.

## Real import inserted row count
**431** `urban_goodz_sourced_businesses` rows.

## Batch table verification
- `urban_goodz_sourced_businesses` for marker: **431** ✅
- `urban_goodz_import_batches` for marker: **1** ✅

## Imported row safety verification (read-only, post-import)
- **Rows with dangerous modes (delivery/courier/active/public): 0** ✅
- **Exact `['review_only']` rows (age-restricted, no pickup): 80** ✅
- Module 7 (THC) + 8 (Liquor) rows = 78, **all exact `['review_only']`** ✅
- Non-age rows with review-gated pickup/order_anywhere (allowed): 351 ✅
- **All 431 rows contain `review_only`** ✅
- **partnered tag false on 431/431** ✅
- **onboarding_status = pending_review on 431/431** ✅
- **source_status = admin_import on 431/431** ✅
- category_ids empty/pending: 384; resolved: 47 (52 all-row incl. excluded) ✅

## Age-restricted handling verification
80 inserted age-restricted rows (THC/Liquor + description-term matches)
are **exactly `['review_only']`** — no delivery, courier, pickup, or
activation. 86 total age rows existed pre-filter; 6 were also in the
44 by-design exclusions and were skipped.

## category_ids pending verification
384 candidate rows stored `category_ids = []` (honest, no fake ids).
47 candidate rows got exact subcategory→granular matches. No
`category_ids=[1]`, no fabricated ids, no categories/modules created.

## Excluded invalid URL confirmation
**26 rows** excluded by design — never imported. No fabricated URLs.

## Excluded duplicate confirmation
**18 duplicate candidates** (incl. 4 `db_store_*` live-store matches:
The Breakfast Klub, ChopnBlok, Sweet Georgia Brown, Distant Relatives)
excluded — never imported; no live stores merged/overwritten.

## Confirmation — no stores/items/vendors/products created
- stores total unchanged vs pre-existing (read-only check: no new inserts).
- items/products total unchanged.
- vendors (module 2 stores) count unchanged.
- Importer targets only `urban_goodz_sourced_businesses` +
  `urban_goodz_import_batches`.

## Confirmation — no activation/publishing/deploy occurred
- No `publishApprovedListings` call.
- No listing activation; all rows `pending_review`, private, draft.
- No deployment.

## Rollback plan path
`docs/urban-goodz/data-import/session-1-p2b/P3B_ROLLBACK_PLAN_urban_goodz_session1_p3_staging_import_20260709_001.md`
(431 rows exist — do NOT execute unless PM instructs.)

## Remaining risks/blockers
1. **431 rows are now staged as review-eligible drafts** pending admin
   review/activation (expected; not a blocker).
2. 26 invalid URLs + 18 duplicates remain excluded (by design).
3. Module 14/15 taxonomy defect (beauty cats misfiled) — separate
   non-blocking ticket, needed before customer-facing store provisioning.

## PM recommendation
- **Staging import is COMPLETE** for the 431 approved candidates.
- Route the 431 `urban_goodz_sourced_businesses` rows into admin
  review (assign granular category_ids, activate only after review).
- Age-restricted rows stay `review_only` — never delivery/courier-enabled.
- Keep the 26 URL rows + 18 duplicates excluded; handle via separate
  backlog/review.
- File the taxonomy-defect follow-up before any store creation.
- Rollback (P3B_ROLLBACK_PLAN_...) remains available if PM later
  instructs removal.
