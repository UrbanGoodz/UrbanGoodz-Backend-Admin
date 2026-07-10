# P4 TAXONOMY DEFECT TICKET
## Urban Goodz — Session 1 / Data Import
**Severity:** Medium (non-blocking for staging review)
**Status:** Open — do NOT fix in P4 unless separately approved.

### Summary
The Urban Goodz taxonomy (modules ↔ categories) has defects that affect
**category assignment accuracy** for a subset of businesses. These defects
do **not** block the P4 admin review workflow for the 431 staged
`urban_goodz_sourced_businesses` rows (reviewers can still assign
module-correct category IDs manually). They MUST be resolved before any
customer-facing **store provisioning / activation**, because wrong
category IDs would surface incorrect catalogs to customers.

### Defects
1. **Empty granular categories for modules 14 & 15.**
   - Module 14 (Beauty Supply / Hair Providers → mapped from CSV
     "Beauty Supply/Hair Providerz") and module 15 have **zero**
     granular sub-categories seeded. Businesses in these modules cannot
     be assigned any granular `category_ids` today; they remain
     `[]` (pending) and rely on the module-level categorization only.

2. **Beauty categories 820–839 misfiled under module 13 (Retail/Shopping).**
   - Categories with IDs 820–839 (belonging to the Beauty/Personal Care
     domain) are currently attached to module 13 (Retail/Shopping)
     instead of the correct Beauty module (16: Beauty/Personal Care).
   - Any automated mapping or admin selection that pulls
     "categories where module_id = 16" will miss 820–839; a reviewer
     selecting by scanning module 13 will mis-categorize Beauty
     businesses as Retail.

### Impact
- For P3B staged rows: 24 Beauty Supply (module 14) + 35 Beauty/Personal
  Care (module 16) businesses are affected by defect #1 (no granular cats)
  and defect #2 affects any Beauty row whose correct cat falls in 820–839.
- Manual review (P4) can still assign only module-correct IDs; the
  controller explicitly rejects category IDs that do not belong to the
  row's module, so a misfiled 820–839 cannot be assigned to a non-Retail
  row by accident.

### Recommended fix (separate ticket / approval)
1. Seed granular categories for modules 14 and 15.
2. Re-home categories 820–839 from module 13 → module 16 (Beauty).
3. After fix, re-run a backfill of `category_ids` for staged rows flagged
   `category_ids=[]` whose module now has matching categories.
4. Add a data-integrity check: categories must not exist under a module
   whose name/dimension does not match.

### Locks
- This ticket is **documentation only** for P4.
- No taxonomy tables (modules/categories) were modified in P4.
- No migration was run in P4.
