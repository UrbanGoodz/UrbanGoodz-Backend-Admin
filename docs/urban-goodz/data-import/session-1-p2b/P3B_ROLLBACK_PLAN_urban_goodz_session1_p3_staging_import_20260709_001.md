# P3B ROLLBACK PLAN
## Marker: urban_goodz_session1_p3_staging_import_20260709_001

**Status: 431 rows WERE inserted** in P3B real import. Rollback is
available but must NOT be executed unless PM instructs.

If PM instructs rollback, run:

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
