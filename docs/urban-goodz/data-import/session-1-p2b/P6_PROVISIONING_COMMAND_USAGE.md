# P6 PROVISIONING COMMAND USAGE
## `php artisan urban-goodz:sourced-business-provision`

Guarded provisioning of approved Urban Goodz sourced businesses into stores.
**Dry-run by default.** `--apply` is **NOT approved yet** — do not run it
until PM approves the provisioning phase.

### Eligibility gates (per row, required)
- `admin_review_status = approved`
- `category_ids` not empty
- `category_ids` does NOT include `1`
- `category_ids` all match the row's `module_id`
- `source_url` valid (http/https)
- module is **active**
- not age-restricted (`fulfillment_modes` not exactly `['review_only']`)
- not `duplicate` / `merge_required`
- `partnered = false`; `visibility = private` until activation phase

If the eligible count != `--expected-count`, the command **refuses**.

### Dry-run (recommended first, and the only mode run in P5B/P6)
```bash
php artisan urban-goodz:sourced-business-provision \
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 \
  --expected-count=32 --dry-run
```
Prints the 32 rows that WOULD become stores (private, partnered=false) and a
summary (stores=32, vendors=0, items=0, public activation=false). Writes nothing.

### Apply (NOT APPROVED YET — do not run without PM sign-off)
```bash
php artisan urban-goodz:sourced-business-provision \
  --batch-marker=urban_goodz_session1_p3_staging_import_20260709_001 \
  --expected-count=32 --apply
```
Would create 32 stores via `UrbanGoodzIngestionService::publishApprovedListings()`,
which is itself gated (see below). Requires PM approval of the provisioning phase.

### Safety locks
- `publishApprovedListings()` now enforces `provisionEligibilityFailures()`
  and **throws** if a row is ineligible — so the API
  `UrbanGoodzDiscoveryController` `approve` action can no longer bypass the
  rules.
- No `products`/`items` are created unless separately approved.
- `partnered` stays false; `visibility` stays private unless a separate
  activation flag is approved.
- No customer-facing publication unless PM approves.

### Rollback / disable (after any apply)
- Per store: set `stores.status=0`, `stores.active=false`; revert sourced
  `onboarding_status` / `admin_review_status`.
- Or re-run the P3B batch rollback and re-stage.

### publishApprovedListings gate (patched)
`app/Services/UrbanGoodzIngestionService.php::publishApprovedListings()` now
calls `provisionEligibilityFailures()` and throws `RuntimeException` on any
failure (not approved / empty or `[1]` category_ids / module mismatch /
invalid URL / inactive module / age-restricted / partnered). Verified: an
age-restricted `[]` row was refused and no store was created.
