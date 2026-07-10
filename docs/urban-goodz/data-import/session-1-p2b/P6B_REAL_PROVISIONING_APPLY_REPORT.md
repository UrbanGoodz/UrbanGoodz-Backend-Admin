# P6B REAL PROVISIONING APPLY REPORT
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Status:** APPLIED — exactly 32 private/inactive stores provisioned for the
32 approved fast-track rows. No public activation, no vendors/products/items.

---

## 1. Preflight counts (before apply)
- approved sourced rows: **32** · pending: **399**
- stores before: **130** · vendors: **130** · items: **1116**

## 2. Fresh dry-run results
`--expected-count=32 --dry-run` → eligible **32**; stores to create **32**;
vendors **0**; products/items **0**; age-restricted **0**; inactive-module
**0**; category_ids=[] **0**; partnered **false**; visibility **private**;
public activation **false**. Exact match → apply authorized.

## 3. Apply command / results
`--expected-count=32 --apply` → created **28** private stores; idempotently
skipped **4** already-provisioned from the first attempt (total **32**).
(A first attempt partially failed on a `stores.phone` unique constraint;
the command was hardened with a unique synthetic phone + idempotent skip,
then completed.)

## 4. Created store IDs (32)
133, 134, 135, 136, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147,
148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161,
162, 163, 164, 165

## 5. Source row IDs provisioned (32)
2, 20, 28, 31, 32, 54, 59, 63, 64, 65, 93, 119, 120, 137, 142, 143, 155,
166, 169, 177, 182, 198, 210, 213, 218, 241, 257, 258, 280, 286, 299, 430
(all now `onboarding_status = provisioned_private`, `admin_review_status = approved`)

## 6. Safety verification (post-apply)
- **Exactly 32 stores created** (stores total 130 → 162; 32 private/inactive).
- **0 vendors** (130), **0 products/items** (1116) created.
- All 32 stores: `status=0`, `active=false`, `is_partner=false`,
  `delivery=false`, `take_away=false`, `is_public_sourced=true` → private,
  non-public, not partnered.
- **0 active/public stores** among the provisioned set.
- **0 age-restricted**, **0 inactive-module (module 14)**, **0 category_ids=[]**
  rows were included (those buckets remain 80 / 30 / 373 respectively).
- `publishApprovedListings()` gate still refuses ineligible rows (verified
  in P5B; unchanged).

## 7. Rollback / disable plan path
`docs/urban-goodz/data-import/session-1-p2b/P6B_PROVISIONING_ROLLBACK_DISABLE_PLAN.md`
(preferred disable = keep inactive + revert `onboarding_status`; emergency
hard-delete fallback only if PM instructs; scoped to exact IDs; touches no
unrelated stores/vendors/items).

## 8. Remaining blockers
- 373 `category_ids=[]` rows: still manual review.
- 80 age-restricted rows: still compliance review.
- 30 module-14 (inactive) rows: still blocked until PM module activation.

## 9. PM recommendation
- 32 fast-track private stores are provisioned and safe (inactive,
  non-partnered, non-public).
- Do NOT publicly activate until a separate, PM-approved activation phase.
- Process the remaining buckets (373 / 80 / 30) before any broader
  provisioning; any further provisioning must reuse the guarded, gated
  command (dry-run first, exact `--expected-count`).
- Keep the rollback plan on hand; do not execute unless instructed.
