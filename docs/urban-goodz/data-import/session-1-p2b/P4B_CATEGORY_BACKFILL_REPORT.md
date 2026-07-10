# P4B CATEGORY BACKFILL REPORT
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Status:** APPLIED — exact-match backfill of **11** staged rows only.

---

## 1. Inspection findings before coding
- 384 staged rows had `category_ids = []` (pending) after P3B import.
- P4A analysis identified **11 exact auto-matchable** rows (subcategory
  token in `tags[1]` exactly matches a granular category under the row's
  module by slug/normalized name).
- The remaining **373** rows have no exact match and stay `[]` (manual).
- No store/vendor/product provisioning is involved; this phase only writes
  `urban_goodz_sourced_businesses.category_ids`.

## 2. Exact 11 rows backfilled
| id | business_name | module | subcat | → category |
|----|--------------|--------|--------|-----------|
| 6 | River Oaks Galleria Medspa | 14 | cosmetics | 824 Cosmetics |
| 23 | Simply Mel Skincare | 14 | skincare | 823 Skin Care |
| 28 | Seven Thirteen Boutique | 13 | womens_fashion | 98 Women's Fashion |
| 108 | Glow Skincare Studio | 14 | skincare | 823 Skin Care |
| 259 | Suga Glow Beauty Studio | 14 | skincare | 823 Skin Care |
| 320 | Bnear Studios | 14 | beauty_personal_care | 820 Beauty / Personal Care |
| 321 | The Den Salon | 14 | beauty_personal_care | 820 Beauty / Personal Care |
| 322 | Knoel LauRen Hair Salon | 14 | beauty_personal_care | 820 Beauty / Personal Care |
| 323 | Magnificent Brothers Barber & Beauty Salon | 14 | beauty_personal_care | 820 Beauty / Personal Care |
| 324 | Eros' Hair & Beauty | 14 | beauty_personal_care | 820 Beauty / Personal Care |
| 349 | Made By GEbony | 14 | beauty_personal_care | 820 Beauty / Personal Care |

(See `P4B_CATEGORY_BACKFILL_EXACT_MATCHES.csv` for machine-readable form.)

## 3. Category IDs assigned
Each of the 11 rows received a single, module-correct `category_ids`
(e.g. `[824]`, `[823]`, `[98]`, `[820]`). No `[1]` fallback, no fabrication.

## 4. Dry-run command / results
```bash
php artisan urban-goodz:sourced-business-category-backfill --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 --dry-run
```
→ 11 exact matches; pending before 384; manual remaining 373; 0 writes.

## 5. Apply command / results
```bash
php artisan urban-goodz:sourced-business-category-backfill --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 --apply
```
→ **Rows updated: 11 (expected 11)**; pending after **373**; 0 other rows touched.

## 6. Before / after category_ids pending counts
- Before: **384** pending
- After: **373** pending (−11)
- Resolved total (P3B 47 + P4B 11): **58**

## 7. Confirmation 373 rows remain manual
Verified: `WHERE created_by_source = marker AND JSON_LENGTH(category_ids)=0`
count = **373** after apply.

## 8. Safety confirmation
- Command default dry-run; `--apply` required.
- Refused unless exactly 11 matches; per-row guards: category module_id ==
  row module_id, category id != 1, row previously empty, row within batch
  marker.
- Only `urban_goodz_sourced_businesses.category_ids` updated. **No**
  stores/items/vendors/products touched. **No** activation, no
  publishApprovedListings, no module changes, no migration.

## 9. Rollback plan (this phase only)
```sql
UPDATE urban_goodz_sourced_businesses
SET category_ids = '[]'
WHERE id IN (6, 23, 28, 108, 259, 320, 321, 322, 323, 324, 349)
  AND created_by_source = 'urban_goodz_session1_p3_staging_import_20260709_001';
```
Restores only the 11 P4B rows to `[]`. Does NOT touch stores, items/
products, vendors, other sourced rows, import batches, users, orders, or
payments. Do NOT execute unless PM instructs.

## 10. PM recommendation
- P4B exact backfill complete and verified (11/11, pending 384→373).
- Keep the 373 manual rows `[]` for admin review via the P4 queue.
- Do not fuzzy-backfill; any further auto-matching requires a new,
  separately-approved guarded command with its own exact-match rules.
- Module 14 activation and module 15 resolution remain separate PM
  decisions.
