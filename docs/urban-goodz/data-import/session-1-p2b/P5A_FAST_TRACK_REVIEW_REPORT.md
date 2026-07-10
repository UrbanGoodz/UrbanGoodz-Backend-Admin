# P5A FAST-TRACK REVIEW REPORT
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Status:** Packaging/guidance only. No `admin_review_status` changed by script; no stores/vendors/products created.

---

## 1. Fast-track row count
**32** rows meet every technical eligibility gate except the human approval.
(Stored `admin_review_status` for all 431 is `pending`; the queue presents
this as the "pending" option. The task's "pending_review" maps to DB `pending`.)

## 2. Fast-track inclusion criteria (all required)
batch marker ✓ · `admin_review_status = pending` ✓ · non-empty `category_ids`
(no `1`, module-matched) ✓ · valid `source_url` ✓ · **active module** ✓ ·
not age-restricted ✓ · no dangerous modes ✓ · not duplicate/merge_required ✓ ·
`partnered = false` ✓ · private ✓.

## 3. Fast-track exclusion (399 rows) and why
| Bucket | Count | Reason excluded |
|--------|-------|----------------|
| `category_ids = []` | 373 | Need category assignment before provisioning |
| Age-restricted `review_only` | 80 | Need separate compliance approval |
| Inactive module (module 14) | 30 | Need PM module-activation decision |
| (overlap) | — | Buckets overlap; total excluded = 399, fast-track = 32 |

Note: the 4 live-store duplicate matches and 26 invalid-URL rows were
excluded at import and are **not** in the 431 batch.

## 4. Review instructions
- Use `P5A_FAST_TRACK_ADMIN_REVIEW_ROWS.csv` as the worklist; each row has a
  direct `admin_review_url` into the P4 queue.
- Admin opens each, verifies name/address/URL/category, then sets
  `admin_review_status` to `approved` / `rejected` / `merge_required`.
- Follow `P5A_ADMIN_REVIEW_SPRINT_GUIDE.md` for accept/reject/merge rules.
- Approval is a review decision ONLY — it does not provision a store.

## 5. Safety locks confirmed
- No `admin_review_status` changed programmatically in P5A.
- No stores/vendors/items/products created; no activation/publishing.
- `partnered` untouched; visibility stays private.
- P4 queue does not call `publishApprovedListings`.
- Provisioning remains a separate, PM-approved, `--dry-run`-gated phase.

## 6. Next phase recommendation
- Run the admin sprint on the 32 fast-track rows.
- Separately process the 373 `[]` rows (assign `category_ids` in the P4
  queue) and the 80 age-restricted rows (compliance review).
- PM decides module 14 activation so its 30 rows can enter a future
  fast-track.
- Only after approvals + PM sign-off, build/run a guarded provisioning
  command (dry-run first, exact batch marker).
