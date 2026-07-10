# P6 PROVISIONING DRY-RUN REPORT
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Status:** Dry-run ONLY. **No provisioning `--apply` was run.**

---

## 1. Provisioning command added
`urban-goodz:sourced-business-provision` (dry-run default; `--apply` not
approved in this phase). It targets only `admin_review_status = approved`
rows that also satisfy every eligibility gate, and calls
`UrbanGoodzIngestionService::publishApprovedListings()` per row on apply.

## 2. Dry-run command / results
```bash
php artisan urban-goodz:sourced-business-provision \
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 \
  --expected-count=32 --dry-run
```
→ Eligible count = **32** (matches expected). Listed 32 "STORE WOULD BE
CREATED" entries with `visibility=private`, `partnered=false`.

## 3. Eligible count
**32** approved rows, all with non-empty module-matched `category_ids`,
valid `source_url`, active module, non-age-restricted.

## 4. What would be created (apply mode, not run)
- **stores that would be created: 32**
- vendors that would be created: **0**
- products/items created: **0**
- partnered: **false**
- visibility: **private**
- public activation: **false**

## 5. What remains private / non-public
All 32 provisioning candidates remain `visibility=private`, `partnered=false`,
no public activation. The 399 still-`pending` rows, 80 age-restricted rows,
and 30 inactive-module-14 rows are excluded from provisioning entirely.

## 6. Confirmation — no apply was run
Provisioning `--apply` was **NOT executed**. Only the dry-run ran.

## 7. Confirmation — no stores/vendors/products created in this phase
Verified store count unchanged (130 before/after the dry-run). No
`Store`/`Item`/`Vendor` records were written by P5B/P6.

## 8. Blockers for remaining rows
- 373 `category_ids = []` rows → need category assignment + approval.
- 80 age-restricted rows → need compliance approval.
- 30 module-14 rows → need PM module-14 activation decision.

## 9. Rollback / disable plan (for a future apply)
- Per created store: set `stores.status=0`, `stores.active=false`, and revert
  the sourced business `onboarding_status`/`admin_review_status` as needed.
- Or run the P3B rollback to clear the batch and re-stage.
- The `publishApprovedListings` method now refuses any ineligible row, so
  partial/provisioning accidents are blocked at the service layer.

## 10. PM recommendation
- Approve a separate provisioning phase only after the 32 (and any further
  approved) rows are finalized.
- When approved, run with `--apply` (the gate + `publishApprovedListings`
  guard protect against bypass).
- Keep visibility private and `partnered=false` until a dedicated
  activation/publish phase is PM-approved.
