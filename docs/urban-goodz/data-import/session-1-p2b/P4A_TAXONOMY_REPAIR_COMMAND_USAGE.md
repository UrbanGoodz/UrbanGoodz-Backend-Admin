# P4A TAXONOMY REPAIR COMMAND USAGE
## `php artisan urban-goodz:taxonomy-repair`

Guarded, idempotent, dry-run-by-default repair of the Urban Goodz taxonomy
defect: reassign beauty categories **820–839** from **Retail/Shopping
(module 13)** to **Beauty/Personal Care (module 14)**.

### Safety guarantees
- **Default is dry-run.** Changes require `--apply`.
- **Never deletes** categories or modules.
- **Never alters** stores / items / vendors / products.
- Updates ONLY `categories.module_id` for the exact confirmed id range.
- **Refuses to run** if:
  - modules 13 or 14 are missing,
  - the affected category count ≠ 20 (expected 20 in range 820–839 under module 13),
  - any live `items` row references categories 820–839.

### Dry-run (recommended first)
```bash
php artisan urban-goodz:taxonomy-repair --dry-run
```
Prints source/target modules, the 20 categories to move, predicted
before/after counts, and rollback SQL. Writes nothing.

### Apply
```bash
php artisan urban-goodz:taxonomy-repair --apply
```
Performs `UPDATE categories SET module_id = 14 WHERE id BETWEEN 820 AND 839 AND module_id = 13;`
then prints before/after counts and verifies moved = 20.

### Expected counts
| | Module 13 | Module 14 |
|--|--|--|
| Before | 40 | 0 |
| After  | 20 | 20 |
| Moved  | 20 (expected 20) | |

### Rollback SQL (taxonomy only)
```sql
UPDATE categories SET module_id = 13
WHERE id BETWEEN 820 AND 839 AND module_id = 14;
```
Restores only the 20 taxonomy rows. Does NOT touch stores, items/products,
vendors, sourced businesses, import batches, users, orders, or payments.
Execute only if PM instructs.

### Notes
- The command is a one-time repair utility; it lives at
  `app/Console/Commands/UrbanGoodzTaxonomyRepair.php`.
- Module 15 ("beauty/hair") is intentionally NOT modified (no fabrication).
- Activating module 14 (status 0 → 1) is out of scope and a separate PM decision.
