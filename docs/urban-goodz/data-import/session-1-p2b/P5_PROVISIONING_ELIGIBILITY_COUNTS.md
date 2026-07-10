# P5 PROVISIONING ELIGIBILITY COUNTS
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Generated:** read-only count check (no writes).

| Metric | Count |
|--------|-------|
| Total staged rows | **431** |
| approved rows | **0** |
| pending_review rows | **431** |
| rejected rows | **0** |
| merge_required rows | **0** |
| category_ids resolved rows | **58** |
| category_ids pending rows | **373** |
| age-restricted `review_only` rows | **80** |
| valid `source_url` rows | **431** |
| invalid `source_url` rows (in batch) | **0** |
| rows blocked by inactive module/category | **30** (module 14 inactive; 0 categories active-blocked) |
| rows would-be-eligible IF approved (cat ok + url + non-age + active module + cats match) | **32** |
| rows theoretically eligible for future provisioning NOW | **0** (none `approved` yet) |

### Notes
- All 431 rows remain `pending_review`; therefore **0 are provision-ready today**.
- 30 rows sit on module 14, which is `status = 0` (inactive). They cannot be
  provisioned until module 14 is activated (separate PM decision).
- 80 age-restricted rows require separate compliance approval before provisioning.
- The 373 `category_ids = []` rows must be reviewed/assigned before provisioning.
- "Would-be-eligible-if-approved" (32) is informational only — it assumes a
  future `approved` status and is NOT provision-ready until PM approves the
  provisioning phase and all hard blockers are cleared.
