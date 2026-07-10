# P4A TAXONOMY REPAIR REPORT
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Repair status:** APPLIED (safe, exact, non-destructive)

---

## 1. Inspection findings before coding
- `modules` columns: id, module_name, module_type, status, ... `categories`
  columns: id, name, image, parent_id, position, status, module_id, slug, ...
- `stores` has **no** `category_ids` column (references `module_id` only).
- `items` has both `category_id` (int) and `category_ids` (JSON).

## 2. Modules 13 / 14 / 15 current state (pre-repair)
| id | name | status | granular categories |
|----|------|--------|---------------------|
| 13 | Retail/Shopping | 1 (active) | 40 (incl. 820–839) |
| 14 | Beauty/Personal Care | 0 (inactive) | **0** |
| 15 | beauty/hair | 0 (inactive) | **0** |
| 16 | Beauty Supply/Hair Providers | 1 (active) | 20 |

## 3. Categories 820–839 current state (pre-repair)
20 categories forming a "Beauty / Personal Care" tree under module 13
(Retail/Shopping). `820` = "Beauty / Personal Care" (parent 0); children
821–834 hang off 820; 835–838 off 821; 839 off 822. **All parents are
within the 820–839 set**, so the subtree is internally consistent.

## 4. Proposed repair
Reassign categories **820–839 from module 13 → module 14** (Beauty/Personal
Care). This:
- Fixes defect #2 (beauty cats misfiled under Retail/Shopping).
- Fixes defect #1 for **module 14** (0 → 20 granular categories), giving
  the admin review queue real Beauty/Personal Care categories to assign.
- **Module 15** ("beauty/hair") intentionally left empty — it appears to
  be a legacy/duplicate module; seeding it requires a separate PM decision
  (no fabrication in this phase).

## 5. Dry-run / Apply
- `php artisan urban-goodz:taxonomy-repair --dry-run` → exact 20 rows,
  predicted after module 13=20, module 14=20.
- `php artisan urban-goodz:taxonomy-repair --apply` → **APPLIED**, moved 20/20.

## 6. Before / After counts
| | Module 13 cats | Module 14 cats |
|--|--|--|
| Before | 40 | 0 |
| After | 20 | 20 |

Moved: **20** (expected 20).

## 7. Safety confirmation
- Only `categories.module_id` updated for ids 820–839. **No deletes.**
- No `stores` / `items` / `vendors` / `products` altered.
- `module` rows untouched (status of module 14 left as-is).
- Command refuses to run if: modules 13/14 missing, affected count ≠ 20,
  or any live item references 820–839 (pre check passed: 0 references).

## 8. Live store / item impact check
- `items` using category_ids 820–839: **0**
- `items.category_id` in 820–839: **0**
- `stores` reference `module_id` only (not category FKs); the single store
  with `module_id=13` is unchanged. **Zero impact.**

## 9. Rollback plan (taxonomy only)
```sql
UPDATE categories SET module_id = 13
WHERE id BETWEEN 820 AND 839 AND module_id = 14;
```
Rollback restores only the 20 taxonomy rows. It must NOT touch stores,
items/products, vendors, sourced businesses, import batches, users, orders,
or payments. Do NOT execute unless PM instructs.

## 10. PM recommendation
- Repair is complete and verified.
- Decide on **module 15 ("beauty/hair")**: repurpose (move hair-specific
  cats 821/822/825/830/835–839 into it) or retire. Requires PM approval;
  not done here to avoid fabrication.
- Activating module 14 (status 0) is a separate PM decision and NOT done
  here.
- Proceed to category_ids backfill for the 384 pending staged rows
  (see readiness report); only exact, safe matches should be auto-applied.
