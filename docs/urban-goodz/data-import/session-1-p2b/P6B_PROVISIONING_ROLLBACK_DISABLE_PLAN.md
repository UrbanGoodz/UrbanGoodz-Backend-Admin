# P6B PROVISIONING ROLLBACK / DISABLE PLAN
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Provisioned:** 32 private/inactive stores + 32 sourced rows marked `provisioned_private`.

---

## Exact store IDs created (32)
133, 134, 135, 136, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147,
148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161,
162, 163, 164, 165

## Exact sourced_business IDs provisioned (32)
2, 20, 28, 31, 32, 54, 59, 63, 64, 65, 93, 119, 120, 137, 142, 143, 155,
166, 169, 177, 182, 198, 210, 213, 218, 241, 257, 258, 280, 286, 299, 430

## Safest disable rollback (PREFERED — do not delete)
All 32 stores were created private/inactive (`status=0`, `active=false`).
To disable, ensure they remain inactive (idempotent):
```sql
UPDATE stores
SET status = 0, active = 0
WHERE id IN (133,134,135,136,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,161,162,163,164,165);
```
And revert the sourced rows' internal status (keeps them review-approved,
just not provisioned):
```sql
UPDATE urban_goodz_sourced_businesses
SET onboarding_status = 'pending_review'
WHERE created_by_source = 'urban_goodz_session1_p3_staging_import_20260709_001'
  AND onboarding_status = 'provisioned_private';
```

## Emergency hard-delete fallback (ONLY if PM instructs)
```sql
DELETE FROM stores
WHERE id IN (133,134,135,136,138,139,140,141,142,143,144,145,146,147,148,149,150,151,152,153,154,155,156,157,158,159,160,161,162,163,164,165);
```
Then revert sourced `onboarding_status` as above.

## Guards
- Rollback must NOT touch unrelated stores (scoped by exact IDs above).
- Rollback must NOT touch vendors / items / products (none were created).
- Rollback must NOT touch the 373 `category_ids=[]`, 80 age-restricted,
  or 30 module-14 rows.
- `publishApprovedListings()` remains gated and will refuse ineligible rows.

Do NOT execute rollback unless PM instructs.
