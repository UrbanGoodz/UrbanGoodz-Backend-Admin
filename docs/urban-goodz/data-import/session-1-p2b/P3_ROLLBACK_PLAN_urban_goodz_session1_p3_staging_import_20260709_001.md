# P3 ROLLBACK PLAN
## Marker: urban_goodz_session1_p3_staging_import_20260709_001

**Status: NO rows were inserted.** The real import was blocked by the importer's
all-or-nothing validation guard (see P3_REAL_STAGING_IMPORT_REPORT.md),
so this rollback is currently a no-op.

If a future PM-approved real import for this marker succeeds, run:

```sql
DELETE FROM urban_goodz_sourced_businesses
WHERE created_by_source = 'urban_goodz_session1_p3_staging_import_20260709_001';

DELETE FROM urban_goodz_import_batches
WHERE source_query = 'urban_goodz_session1_p3_staging_import_20260709_001';
```

### Blast radius
- Targets ONLY the two ingestion tables:
  - `urban_goodz_sourced_businesses`
  - `urban_goodz_import_batches`
- MUST NOT touch:
  - stores
  - items / products
  - vendors
  - zones
  - modules
  - categories
  - unrelated sourced records

### Verification after rollback
- `SELECT COUNT(*) FROM urban_goodz_sourced_businesses WHERE created_by_source = 'urban_goodz_session1_p3_staging_import_20260709_001';` → 0
- `SELECT COUNT(*) FROM urban_goodz_import_batches WHERE source_query = 'urban_goodz_session1_p3_staging_import_20260709_001';` → 0

> Do NOT execute this rollback unless PM instructs.
