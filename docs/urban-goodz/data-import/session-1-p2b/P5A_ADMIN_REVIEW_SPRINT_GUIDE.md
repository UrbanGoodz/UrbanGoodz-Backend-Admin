# P5A ADMIN REVIEW SPRINT GUIDE
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Scope:** 32 fast-track rows (see `P5A_FAST_TRACK_ADMIN_REVIEW_ROWS.csv`)
**This phase:** review packaging/guidance ONLY. **No store creation, no activation, no status changes by script.**

---

## Sprint goal
Give admins a clean, low-risk first batch to review in the P4 queue. These
32 rows already satisfy every technical eligibility gate *except* the human
`approved` decision — so an admin can review and approve them quickly.
Approval is a **review decision only**; it does NOT create a store.

## Exact 32-row scope
Rows that meet ALL of:
- `admin_review_status = pending` (stored DB value; the queue shows it as the "pending" option)
- `category_ids` not empty, no `1`, and every id belongs to the row's module
- valid `source_url`
- module is **active** (excludes the 30 module-14 inactive rows)
- not age-restricted (`fulfillment_modes` not exactly `['review_only']`)
- no dangerous fulfillment modes; no duplicate/live-store conflict
- `partnered = false`; private/non-public

## What admin should check before approving
1. The business name/address/city/state look correct and match the `source_url`.
2. The assigned `category_ids` is the right granular category for this business.
3. The module is correct for the business type.
4. There is no duplicate already live (the 4 known live-store matches were excluded at import, but double-check).
5. Age-restricted status is correct (these 32 are explicitly non-age).

## What admin should reject
- Business is fake, closed, or clearly mis-categorized.
- `source_url` is a placeholder or points to the wrong business.
- Category assignment is wrong and no better category exists (reject or send back).

## What admin should mark `merge_required`
- The business is a clear duplicate of another **staged** row (not a live store).
- Two staged rows represent the same real business and should be merged
  before any future provisioning.

## What must stay `pending`
- All 373 `category_ids = []` rows (need category assignment first).
- All 80 age-restricted rows (need compliance review).
- All 30 module-14 inactive rows (need PM module-activation decision).
- Any row the admin is unsure about — leave pending, do not force-approve.

## Reminders
- **Approving does NOT create a store/vendor/product.** It only records the
  admin review decision (`admin_review_status = approved`).
- **Provisioning requires a separate PM-approved phase** with a guarded,
  `--dry-run`-first command scoped to the exact batch marker.
- Do not mark `partnered`; do not change visibility to public.
