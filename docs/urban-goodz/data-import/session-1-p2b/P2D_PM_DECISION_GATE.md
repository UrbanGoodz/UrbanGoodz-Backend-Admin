# P2D PM DECISION GATE
## Urban Goodz — Phase Data-Import P2D

**Recommended first real staging import scope** (to be executed ONLY after explicit
PM approval and `--dry-run` removal — not performed in this phase).

### Import only the 431 candidates already cleared by P2B
- These passed taxonomy (475 mapped / 0 held), zone (475 valid),
  age-restricted review-only (86), and the source-URL + duplicate gates.
- Importer targets **only** `urban_goodz_sourced_businesses` and
  `urban_goodz_import_batches`.

### Keep excluded from the first real staging import
- **The 26 invalid source_url rows** — stay excluded / manual-review backlog.
  No fabricated URLs.
- **The 18 duplicate candidates** — stay excluded unless PM manually approves
  specific rows.
  - The **4 `db_store_*` live-store matches**
    (The Breakfast Klub, ChopnBlok, Sweet Georgia Brown, Distant Relatives)
    stay excluded from the first import and require separate PM review.
  - Do NOT merge, overwrite, or update the live stores.

### Required fixed attributes for the import
- **partnered_status = false** (never marked partnered).
- **visibility = private** (never public).
- **mode = draft** (never activated).
- **age-restricted rows remain `review_only`** (86 rows: no delivery, courier,
  pickup, or public activation).
- **category_ids = []** for pending rows (423 all-row / 384 candidate)
  until admin review assigns granular taxonomy.

### Safety
- Use a **fresh, explicit `--batch-marker`** for the real import.
- **Do NOT touch** stores / items / products / vendors / zones / modules /
  categories.
- Rollback targets only the batch marker:
  ```sql
  DELETE FROM urban_goodz_sourced_businesses WHERE created_by_source = '<M>';
  DELETE FROM urban_goodz_import_batches WHERE source_query = '<M>';
  ```

### Sign-off
- [ ] 26 invalid URL rows stay excluded/backlog
- [ ] 18 duplicate candidates stay excluded (4 store matches PM-reviewed)
- [ ] partnered_status=false / visibility=private / mode=draft
- [ ] age-restricted rows review_only
- [ ] category_ids=[] acceptable for pending rows
- [ ] fresh batch marker used; --dry-run removal PM-approved
- [ ] rollback rehearsed against exact marker

**PM decision:** ________________  (approve / hold)
**Approved by:** ____________  **Date:** __________
