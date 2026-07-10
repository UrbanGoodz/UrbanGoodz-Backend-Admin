# P5B FAST-TRACK APPROVAL REPORT
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Status:** APPLIED — exactly the 32 P5A fast-track rows set to `admin_review_status = approved`.

---

## 1. Exact 32 IDs approved
2, 20, 28, 31, 32, 54, 59, 63, 64, 65, 93, 119, 120, 137, 142, 143, 155,
166, 169, 177, 182, 198, 210, 213, 218, 241, 257, 258, 280, 286, 299, 430.

(All verified against the P5A CSV source of truth.)

## 2. Approval dry-run command / results
```bash
php artisan urban-goodz:sourced-business-fasttrack-approve \
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 \
  --csv="docs/urban-goodz/data-import/session-1-p2b/P5A_FAST_TRACK_ADMIN_REVIEW_ROWS.csv" --dry-run
```
→ 32 eligible rows listed; predicted approved=32, pending=399; rollback SQL printed; no writes.

## 3. Approval apply command / results
```bash
php artisan urban-goodz:sourced-business-fasttrack-approve \
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 \
  --csv="docs/urban-goodz/data-import/session-1-p2b/P5A_FAST_TRACK_ADMIN_REVIEW_ROWS.csv" --apply
```
→ **Approvals written: 32 (expected 32)**; approved=32, pending=399.

## 4. Before / after review status counts
| | before | after |
|--|--|--|
| approved | 0 | **32** |
| pending | 431 | **399** |
| rejected | 0 | 0 |
| merge_required | 0 | 0 |

## 5. Rollback SQL (approval only)
```sql
UPDATE urban_goodz_sourced_businesses
SET admin_review_status = 'pending'
WHERE id IN (2,20,28,31,32,54,59,63,64,65,93,119,120,137,142,143,155,166,169,177,182,198,210,213,218,241,257,258,280,286,299,430)
  AND created_by_source = 'urban_goodz_session1_p3_staging_import_20260709_001'
  AND admin_review_status = 'approved';
```
Do NOT execute unless PM instructs.

## 6. Safety confirmation
- Only `admin_review_status` was updated — **no stores/vendors/items/products created**.
- Command refused unless CSV==32, DB eligible==32, and every row passed all
  guards (pending status, non-empty module-matched `category_ids`, valid URL,
  active module, non-age, not partnered).
- `partnered` untouched; visibility stays private.

## 7. PM recommendation
- The 32 fast-track rows are now review-approved and eligible for a future,
  separately-approved provisioning run.
- Keep the 373 `[]`, 80 age-restricted, and 30 module-14 rows out of
  provisioning until their own gates clear.
- Do NOT run provisioning `--apply` until PM approves that phase.
