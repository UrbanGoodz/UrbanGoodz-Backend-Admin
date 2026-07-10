# P2B FINAL DRY-RUN READINESS REPORT (PACKAGED)
## Urban Goodz — Data Import / Business Listing Staging
### Phase Data-Import P2B — Final Dry-Run Import Readiness + Backlog/Duplicate Report Packaging

**Status:** P2B ACCEPTED / CLOSED (Session 1)
**Live import:** NOT run. `--dry-run` was NOT removed.
**Importer:** `app/Console/Commands/ImportUrbanGoodzCleanedBusinesses.php`
**Command:** `php artisan urban-goodz:business-import-cleaned --zip=<path> --dry-run --batch-marker=phase_data_import_p2b_final_dry_run`
**Batch marker (this run):** `phase_data_import_p2b_final_dry_run`
**Report date:** 2026-07-09

---

## DRY-RUN EXECUTION SUMMARY

| Metric | Count |
|---|---|
| Verified rows | 20 |
| Partial rows | 455 |
| Backlog excluded (do_not_import) | 104 |
| Total scanned | 475 |
| **Candidate import count** | **431** |
| **Excluded count (non-fatal)** | **44** |
| Zone valid / invalid / mismatched | 475 / 0 / 0 |
| Taxonomy mapped / held | 475 / 0 |
| category_ids resolved | 52 |
| category_ids pending (all-row) | 423 |
| Candidate category_ids pending | 384 |
| Age-restricted review-only | 86 |
| Records written | **0** |

**Command executed (dry-run only):**
```bash
php artisan urban-goodz:business-import-cleaned \
  --zip=storage/app/urban_goodz_import_tmp/9f444f6a147751360f88e1961e2a2f2e/URBAN_GOODZ_ALL_ZONES_BUSINESS_ENRICHED.zip \
  --dry-run \
  --batch-marker=phase_data_import_p2b_final_dry_run
```

**Dry-run output confirmation:**
- `DRY RUN ONLY: no records were written.`
- `Live stores/items/vendors created: 0`
- `Dry run ready: real import would stage 431 candidate row(s) and skip 44 by-design exclusion(s).`

---

## IMPORT READINESS SUMMARY

**431 candidates** cleared the taxonomy + source-URL + duplicate gates:
- 20 verified + 411 partial rows
- All 12 CSV categories map to a real module (475 mapped, 0 held)
- All 475 zones valid (0 invalid, 0 mismatched)
- 86 age-restricted rows correctly forced to `review_only`
- 52 rows have resolved granular `category_ids`; 384 candidates pending admin review

**44 excluded** (non-fatal, by-design):
- 26 missing/invalid source URLs → backlog
- 18 duplicate candidates → excluded (4 require PM review)

**No fatal validation failures.** The dry-run would proceed to real import if `--dry-run` is removed and PM approves.

---

## BLOCKER SUMMARY

| # | Blocker | Count | Resolution Required |
|---|---|---|---|
| 1 | Invalid Google placeholder source URLs | 26 | Stay excluded; move to manual-review backlog. Do NOT fabricate URLs. |
| 2 | Live-store duplicate matches (`db_store_*`) | 4 | **PM review required** before any real import. Do NOT merge/overwrite live stores. |
| 3 | CSV-internal duplicates | 14 | Auto-excluded; no PM action needed. |
| 4 | DB taxonomy defects (modules 14/15 empty; beauty cats 820–839 misfiled under module 13) | — | Separate ticket (P4_TAXONOMY_DEFECT_TICKET.md). Does NOT block staging import. |

---

## BACKLOG LIST — INVALID / PLACEHOLDER SOURCE URLs (26 rows)

**Source file:** `excluded_source_url_p2b.csv`  
**Exclusion reason:** `missing_or_invalid_source_url` (PHP `FILTER_VALIDATE_URL` fails due to unencoded spaces in Google search query strings)

All 26 rows carry Google-search placeholder URLs, e.g.:
```
https://www.google.com/search?q=Island%20Courier%20Services+Texas City+TX
```
The space in `Texas City+TX` breaks URL validation. These are **NOT real business URLs** — they are search-query references left over from sourcing.

### Affected by market:
- **Wharton/Brazoria, TX (5):** Island Courier Services, Eaglin Motor Lines, Texas City Soul Food, Bay City BBQ, Sha BeBe Cajun Cafe
- **Los Angeles, CA (7):** The Blk Lifestyle, ConditionHER Hair, Contented Nail Parlor, Britt Brow, Crystal Eyes Healing, Mae's Courier LA, Soul 2 Soul Global Events
- **Las Vegas, NV (6):** 1QTEE Boutique, Slauson Grill, Hibachi Vegas Food Truck, Afiya Express Courier, Sweets by Sherell, Vegas Health First Pharmacy
- **New York, NY (8):** Harlem Health Pharmacy, Big Apple Courier, Harlem Events Collective, Piece of Cake Bakery, Harlem Nail Studio, Brooklyn Barber Co., The Brownstone Event Space, Uptown Mobile Deli

### Handling rules (enforced):
1. **Do NOT fabricate or generate replacement source URLs.**
2. Keep these rows **excluded** from the first real staging import.
3. Route to a **manual-review backlog** keyed by business name + market.
4. For each, source a **real, verifiable URL** (official website, Google Business Profile, verified social) before any future import.

**Full detail:** `INVALID_SOURCE_URL_BACKLOG.md`, `excluded_source_url_p2b.csv`

---

## DUPLICATE CANDIDATE REPORT (18 rows)

**Source file:** `duplicates_p2b.csv`  
**Exclusion reason:** `duplicate_candidate`

### Breakdown:
| Type | Count | Action |
|---|---|---|
| CSV-internal duplicates (name/city/state, website, or phone within import set) | 14 | Auto-excluded; remain excluded. No PM review needed. |
| Live-store `db_store_*` matches (existing `stores` table) | 4 | **PM review required.** Do NOT merge/overwrite live stores. |

### The 4 live-store matches (PM REVIEW REQUIRED):

| Row | Business | City/State | Match Reason |
|---|---|---|---|
| 3 | The Breakfast Klub | Houston, TX | `db_store_duplicate_name_city_or_address` |
| 23 | ChopnBlok | Houston, TX | `db_store_duplicate_name_city_or_address` |
| 118 | Sweet Georgia Brown | Dallas, TX | `db_store_duplicate_name_city_or_address` |
| 146 | Distant Relatives | Austin, TX | `db_store_duplicate_name_city_or_address` |

### CSV-internal duplicates (14, auto-excluded):
- Erk and Jerk Caribbean Cuisine (Houston, TX) — duplicate phone
- Amazing Street Beans HTX (Houston, TX) — duplicate name/city/state
- Sauce Another HTX (Houston, TX) — duplicate name/city/state, website, phone
- Fresh Houwse Grocery (Houston, TX) — duplicate name/city/state, website
- Motherland African Food Market (Houston, TX) — duplicate name/city/state
- Da Vegan Guru (Memphis, TN) — duplicate website
- Doss Couture Designs (Houston, TX) — duplicate website
- The Black Market HTX (Houston, TX) — duplicate name/city/state
- Earnestine & Hazel's (Memphis, TN) — duplicate name/city/state, phone
- Texas Original - Dallas (Dallas, TX) — duplicate website, phone
- Goodblend Texas - Dallas (Dallas, TX) — duplicate website, phone
- CBD Plus USA - Dallas (Dallas, TX) — duplicate website
- Texas Original - Austin (Austin, TX) — duplicate website, phone
- Your CBD Store - Charlotte (Charlotte, NC) — duplicate website

**PM instruction:** All 18 duplicates stay excluded from the first real staging import. The 4 live-store matches additionally require separate PM review before any future reconsideration. The staging import only writes to `urban_goodz_sourced_businesses` (review table) and must never modify `stores`.

**Full detail:** `DUPLICATE_REVIEW_REQUIRED.md`, `duplicates_p2b.csv`

---

## AGE-RESTRICTED HANDLING (86 review-only rows)

**Source file:** `age_restricted_p2b.csv`  
- **Liquor/Beverages:** 30 candidates (all forced `fulfillment_modes = ['review_only']`)
- **THC/Dispensary:** 54 candidates (all forced `review_only`)
- +2 via description terms (Pharmacy/Health with CBD/vape/tobacco terms)

**Enforced flags:**
- `--disable-age-restricted-fulfillment=true`
- No delivery, courier, pickup, or public activation for these rows.
- `onboarding_status = pending_review`, `admin_review_status = pending`

**Full detail:** `age_restricted_p2b.csv` (80 candidates + 6 also excluded)

---

## TAXONOMY / CATEGORY_IDS SUMMARY

| Metric | Count |
|---|---|
| CSV categories mapped to modules | 12 / 12 (475 rows mapped, 0 held) |
| Rows with resolved granular `category_ids` | 52 |
| Rows with `category_ids = []` pending admin review (all-row) | 423 |
| Candidate rows pending `category_ids` | 384 |

**Known DB taxonomy defects (separate ticket P4_TAXONOMY_DEFECT_TICKET.md):**
- Modules 14 (Beauty/Personal Care) and 15 have 0 granular categories
- Beauty Supply categories 820–839 misfiled under module 13 (Retail/Shopping)
- Does NOT block staging import; `category_ids = []` is honest and review-eligible

**Supporting files:** `taxonomy_summary_p2b.csv`, `category_ids_pending_p2b.csv`, `candidates_p2b.csv`

---

## CONFIRMATION — NO RECORDS IMPORTED

- Dry-run only. Command output: `"DRY RUN ONLY: no records were written"` and `"Live stores/items/vendors created: 0"`
- Importer writes **only** to `urban_goodz_sourced_businesses` and `urban_goodz_import_batches`
- No `Store` / `Item` / `Vendor` / product insertion occurs anywhere in the command
- Rollback is currently a no-op (0 rows for this batch marker)

---

## ROLLBACK PLAN (for future real import)

If a future PM-approved real import runs with batch marker `M`:

```sql
DELETE FROM urban_goodz_sourced_businesses
WHERE created_by_source = '<M>';

DELETE FROM urban_goodz_import_batches
WHERE source_query = '<M>';
```

- Targets **only** the two ingestion tables
- Must NOT touch: stores, items/products, vendors, zones, modules, categories, or unrelated sourced records

---

## PM RECOMMENDATION — REAL IMPORT NOT APPROVED

**Do NOT run a real import under this handoff's rules.**

A real staging import of the 431 candidates may proceed **only if PM explicitly accepts all**:

- [ ] 26 invalid URL rows stay excluded / moved to manual-review backlog (no fabricated URLs)
- [ ] 4 `db_store_*` live-store matches get separate manual PM review (do not merge/overwrite live stores)
- [ ] `category_ids = []` is acceptable for pending rows until admin review assigns granular taxonomy
- [ ] 86 age-restricted rows remain `review_only` (no fulfillment modes enabled)
- [ ] A **fresh, explicit `--batch-marker`** is used (do NOT reuse `phase_data_import_p2b_final_dry_run`)
- [ ] `--dry-run` removal is explicitly PM-approved (not removed casually or by automation)
- [ ] Rollback plan is rehearsed against the exact batch marker

**File a separate ticket** for the module 14/15 empty-category and beauty-category misfiling cleanup (needed before customer-facing store provisioning).

---

## FILES INSPECTED / REFERENCED

| File | Purpose |
|---|---|
| `app/Console/Commands/ImportUrbanGoodzCleanedBusinesses.php` | Importer command (dry-run logic, validation, guards) |
| `docs/urban-goodz/data-import/session-1-p2b/P2B_FINAL_DRY_RUN_PM_SUMMARY.md` | This report (updated) |
| `docs/urban-goodz/data-import/session-1-p2b/excluded_source_url_p2b.csv` | 26 invalid source URL rows (backlog) |
| `docs/urban-goodz/data-import/session-1-p2b/duplicates_p2b.csv` | 18 duplicate candidates |
| `docs/urban-goodz/data-import/session-1-p2b/candidates_p2b.csv` | 431 staging-ready candidates |
| `docs/urban-goodz/data-import/session-1-p2b/age_restricted_p2b.csv` | 86 age-restricted review-only rows |
| `docs/urban-goodz/data-import/session-1-p2b/taxonomy_summary_p2b.csv` | Module mapping counts |
| `docs/urban-goodz/data-import/session-1-p2b/category_ids_pending_p2b.csv` | 384 candidates pending category_ids |
| `docs/urban-goodz/data-import/session-1-p2b/INVALID_SOURCE_URL_BACKLOG.md` | Backlog handling rules |
| `docs/urban-goodz/data-import/session-1-p2b/DUPLICATE_REVIEW_REQUIRED.md` | Duplicate triage rules |
| `docs/urban-goodz/data-import/session-1-p2b/P2D_PM_DECISION_GATE.md` | PM sign-off checklist for real import |
| `docs/urban-goodz/data-import/session-1-p2b/REAL_STAGING_IMPORT_DECISION_CHECKLIST.md` | Execution checklist |
| `storage/app/urban_goodz_import_tmp/9f444f6a147751360f88e1961e2a2f2e/` | Source CSV ZIP extraction (verified/partial/backlog) |

---

## COMMANDS RUN (SAFE, READ-ONLY)

```bash
# Dry-run validation only (no writes)
php artisan urban-goodz:business-import-cleaned \
  --zip=storage/app/urban_goodz_import_tmp/9f444f6a147751360f88e1961e2a2f2e/URBAN_GOODZ_ALL_ZONES_BUSINESS_ENRICHED.zip \
  --dry-run \
  --batch-marker=phase_data_import_p2b_final_dry_run

# Route clear only if needed (route:cache NOT used due to duplicate route-name conflict)
php artisan route:clear
```

---

## FINAL PM HANDOFF

**This report packages the complete P2B dry-run outcome:**
- ✅ 431 candidates staging-ready
- ✅ 26 invalid URLs → backlog (documented, no fabrication)
- ✅ 18 duplicates excluded (4 live-store matches → PM review)
- ✅ 86 age-restricted → `review_only`
- ✅ 0 fatal blockers
- ✅ 0 records written
- ✅ Rollback plan defined

**PM Decision Required:** Check all boxes in `REAL_STAGING_IMPORT_DECISION_CHECKLIST.md` and `P2D_PM_DECISION_GATE.md` before any real import. Remove `--dry-run` **only** with explicit approval and a fresh batch marker.

---

## NEXT RECOMMENDED TASK

**P2C — Real Staging Import Execution (PM-gated)**
1. PM completes decision checklist (`REAL_STAGING_IMPORT_DECISION_CHECKLIST.md`)
2. Run final dry-run to verify counts still match (431 / 44)
3. Remove `--dry-run`, run with new `--batch-marker=phase_data_import_p2c_real_YYYYMMDD_HHMM`
4. Verify row counts post-import against candidate total
5. Confirm rollback SQL works against the new marker

**Do not proceed to P2C until PM signs off on all 7 decision gates above.**

---

*Report generated from P2B dry-run output and supporting CSV/MD artifacts.*
*No live import executed. No records written. No migrations run. No secrets touched.*