# DUPLICATE REVIEW — REQUIRED BEFORE REAL IMPORT
## Urban Goodz — Phase Data-Import P2B

**18 duplicate candidates** were identified by the dry-run validator.

### Breakdown
- **14 CSV-internal duplicates** — same business appears more than once
  within the import set (matched on name|city|state, website, or phone).
  These are safely excluded from the candidate set.
- **4 `db_store_duplicate_*` matches** — these match an
  **existing live store** in the `stores` table. They require manual PM review.

### The 4 live-store matches (do NOT auto-import)
| Business | Note |
|---|---|
| The Breakfast Klub | `db_store_duplicate_name_city_or_address` |
| ChopnBlok | `db_store_duplicate_name_city_or_address` |
| Sweet Georgia Brown | `db_store_duplicate_name_city_or_address` |
| Distant Relatives | `db_store_duplicate_name_city_or_address` |

### PM instruction
- **Do NOT merge, overwrite, or update the live stores** represented by
  these 4 matches without a separate, explicit review.
- The staging import only ever writes to `urban_goodz_sourced_businesses`
  (a review table). It must NOT modify `stores`.
- Decide per match: exclude from import, or import as a separate
  `sourced_business` review record (distinct from the live store).

### Source of truth
- Full duplicate list: `duplicates_p2b.csv`
- Dry-run reason: `duplicate_candidate` (18 total)
