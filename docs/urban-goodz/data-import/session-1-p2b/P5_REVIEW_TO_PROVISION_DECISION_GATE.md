# P5 REVIEW-TO-PROVISION DECISION GATE
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Status:** Decision-gate / readiness ONLY. **No stores, vendors, items/products created. No activation/publishing.**

---

## 1. Current staged batch state
- 431 staged `urban_goodz_sourced_businesses` rows (private/draft/`pending_review`).
- 44 rows excluded at import (26 invalid URL + 18 duplicates incl. 4 live-store matches).
- `admin_review_status`: **431 pending**, 0 approved, 0 rejected, 0 merge_required.
- `category_ids`: **58 resolved**, **373 pending (`[]`)**.
- Age-restricted (`review_only` only): **80** rows.
- Valid `source_url`: **431** (all; the 26 invalid URLs were excluded at import).
- Rows on an **inactive module**: **30** (all module 14, status=0).
- No stores/vendors/items/products created from this import.

## 2. Provisioning eligibility rules (future)
A row is **provision-ready** ONLY if ALL are true:
- Belongs to the P3B batch marker.
- `admin_review_status = approved`.
- `category_ids` NOT empty.
- `category_ids` does NOT include `1`.
- `category_ids` all match the row's `module_id`.
- `source_url` is valid.
- Not `duplicate` / `merge_required`.
- `visibility` is private before provisioning.
- `partnered = false` before provisioning.
- Module is **active** (or explicitly PM-approved for activation/provisioning).
- Age-restricted rows have **separate compliance approval**.
- No dangerous fulfillment modes.
- No live-store conflict.
- **PM explicitly approves the provisioning phase.**

## 3. Hard blockers (row cannot be provisioned)
- `category_ids = []`
- `admin_review_status != approved`
- duplicate / live-store conflict
- invalid `source_url`
- `category_ids` includes `1`
- module/category mismatch
- age-restricted without compliance approval
- inactive module without PM approval
- missing required business fields
- any attempt to create store/vendor/item without provisioning approval

## 4. Soft blockers (resolve before/with provisioning)
- `category_ids` manually assigned but unverified by admin
- module 14 inactive (needs PM activation decision)
- module 15 empty/legacy (unrelated to this batch, but taxonomy hygiene)
- rows still in `pending_review` awaiting admin decision

## 5. Required admin approval steps (future provisioning phase)
1. Admin reviews each row in the P4 queue → sets `admin_review_status`.
2. Admin assigns module-correct `category_ids` (P4 queue supports this).
3. Age-restricted rows routed to compliance review.
4. PM signs off activation of any inactive module used (e.g. module 14).
5. PM approves the provisioning phase and a guarded `--dry-run` provisioning command scoped to the exact batch marker.

## 6. Required safety locks
- `partnered` stays false unless separately approved.
- `visibility` stays private until an explicit activation phase.
- No customer-facing publication until PM approves.
- No store/vendor/product creation until PM approves.
- Any provisioning command MUST have `--dry-run` (default) and require exact `--batch-marker`.
- Rollback/disable plan exists before any provisioning.

## 7. PM decision required before provisioning
- Approve activation of module 14 (currently inactive) if its 30 rows are to be provisioned.
- Approve the provisioning phase and the specific guarded command.
- Approve handling of age-restricted (80) and the 373 manual-review rows.

## 8. Explicit statement
**P5 does NOT create stores, vendors, items/products.** No provisioning command is built in P5. No activation, no publishing, no `partnered` change. This phase only documents eligibility, blockers, and the future checklist.

## 9. Provisioning pipeline note (control)
`UrbanGoodzIngestionService::publishApprovedListings()` exists and is invoked
from `app/Http/Controllers/Api/V1/UrbanGoodzDiscoveryController.php` (admin
action). The **P4 admin review queue does NOT call it** — it only updates
`admin_review_status` + `category_ids`, so no accidental provisioning occurs
from review. The API path should be gated behind the eligibility rules above
before any provisioning is allowed.
