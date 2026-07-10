# REAL STAGING IMPORT — DECISION CHECKLIST
## Urban Goodz — Phase Data-Import P2B → future P2C real import

**This checklist must be fully satisfied and PM-approved BEFORE any real
(non-dry-run) import is executed.**

> Current state: P2B dry-run only. `--dry-run` has NOT been removed.
> No live import has been run.

---

### Pre-import decisions (PM sign-off required)
- [ ] **Confirm 26 invalid URL rows stay excluded / moved to manual-review backlog.**
      They carry Google-search placeholder URLs with unencoded spaces and must
      NOT be fabricated.
- [ ] **Confirm the 4 `db_store_*` duplicate matches are manually reviewed
      before real import:**
      - The Breakfast Klub
      - ChopnBlok
      - Sweet Georgia Brown
      - Distant Relatives
      Do not merge, overwrite, or update the live stores.
- [ ] **Confirm `category_ids = []` is acceptable for pending rows**
      (423 all-row / 384 candidate) until admin review assigns granular taxonomy.
- [ ] **Confirm age-restricted rows remain `review_only`**
      (86 rows: Lior/Beverages 30, THC/Dispensary 54, +2 description terms).
      No delivery, courier, pickup, or public activation.
- [ ] **Confirm a fresh, explicit `--batch-marker` is used for the real import**
      (do NOT reuse the dry-run marker `phase_data_import_p2b_final_dry_run`).
- [ ] **Confirm `--dry-run` removal is explicitly approved by PM** (it must not
      be removed casually or by automation).

### Safety / blast-radius confirmation
- [ ] **Confirm rollback SQL targets the exact batch marker only** (see below).
- [ ] **Confirm no stores / items / products / vendors are touched** by the import.
- [ ] **Confirm no live activation occurs** (listings stay private/draft).
- [ ] **Confirm `partnered_status` remains `false`.**
- [ ] **Confirm `visibility` remains `private` and `mode` remains `draft`.**
- [ ] **Confirm the importer still only writes to `urban_goodz_sourced_businesses`
      and `urban_goodz_import_batches`.**

### Execution (only after all boxes checked)
1. Run with `--dry-run` one final time; verify counts match (431 candidates / 44 excluded).
2. Remove `--dry-run` **only after** PM approval.
3. Use a new explicit marker, e.g. `--batch-marker=phase_data_import_p2c_real_<DATE>_<HHMM>`.
4. Verify row counts post-import against the candidate total.
5. Rehearse/confirm the rollback plan below as a safety net.

### Rollback plan (if needed)
```sql
DELETE FROM urban_goodz_sourced_businesses
WHERE created_by_source = '<BATCH_MARKER>';

DELETE FROM urban_goodz_import_batches
WHERE source_query = '<BATCH_MARKER>';
```
- Targets ONLY the two ingestion tables.
- Must NOT touch: stores, items/products, vendors, zones, modules, categories,
  or unrelated sourced records.

**PM decision:** ________________________________  (approve / hold)
**Approved by:** ________________  **Date:** ____________
