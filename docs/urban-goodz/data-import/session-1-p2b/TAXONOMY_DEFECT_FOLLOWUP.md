# TAXONOMY DEFECT — FOLLOW-UP
## Urban Goodz — Phase Data-Import P2B

This is a **known data-defect** in the taxonomy tables. It does NOT block
the P2B staging import (all 12 CSV categories resolve to a real module),
but it should be fixed **before** customer-facing store provisioning /
taxonomy is used.

### Findings
- **Modules 14 (`Beauty/Personal Care`) and 15 (`beauty/hair`) have
  ZERO granular categories.**
- **Beauty / Personal Care granular categories (IDs 820–839)** — e.g.
  Hair Care, Skin Care, Nail Care, Cosmetics, Barber Supplies, etc. —
  are currently **misfiled under module 13 (`Retail/Shopping`)**.
- The importer's explicit `CSV_CATEGORY_MODULE_MAP` correctly points
  `Beauty / Personal Care` → module 14 and `Beauty Supply / Hair
  Providerz` → module 16, so module resolution works regardless. But the
  underlying category ownership is wrong.

### Impact
- Staging import: **none** — `category_ids` is left `[]` for pending rows
  and assigned during admin review.
- Future store creation / customer-facing browsing: **affected** — beauty
  businesses would show under Retail taxonomy, and modules 14/15 would
  appear empty.

### Required follow-up (separate ticket)
1. Move beauty categories 820–839 from module 13 → module 14
   (`Beauty/Personal Care`) (or 15 where appropriate).
2. Confirm modules 14 and 15 have the intended granular category set.
3. Do NOT auto-create categories during the import — this is a manual
   taxonomy-setup task, not an importer responsibility.

### Source of truth
- DB tables: `modules`, `categories`
- Importer mapping: `CSV_CATEGORY_MODULE_MAP` in
  `app/Console/Commands/ImportUrbanGoodzCleanedBusinesses.php`
