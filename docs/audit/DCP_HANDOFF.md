# DCP Handoff — isolated staging environment

## Environment

| Item | Value |
|------|-------|
| Staging URL | `http://127.0.0.1:8088` |
| Bind address | `127.0.0.1` only (loopback; never exposed) |
| Database | `urbangoodz_isolated_staging_20260723` |
| Schema | 242 tables, 84 foreign keys, zero application data rows |
| Branch | `claude-database-staging-recovery` |
| Worktree | `AdminPanel_Codex_Platform_Audit` |
| Env file | `.env.staging` (untracked — holds credentials) |

`AdminPanel_Update_V39` @ `adminpanel-v39-backend-sprint` is **untouched** and
was verified clean.

> The worktree's `vendor/` is a symlink to the `AdminPanel_Update_V39`
> vendor tree. It is only ever read. `composer dump-autoload` is therefore
> avoided — see "Autoloader caveat" below.

## Commands

```bash
cd AdminPanel_Codex_Platform_Audit

# 1. Boot the staging server (loopback only)
APP_ENV=staging php artisan serve --host=127.0.0.1 --port=8088

# 2. Confirm nothing can reach the outside world (21 checks)
APP_ENV=staging php scripts/audit/staging_safety_check.php

# 3. Seed deterministic role fixtures (idempotent)
export STAGING_FIXTURE_PASSWORD=...        # supplied out of band
APP_ENV=staging php scripts/audit/seed_staging_fixtures.php

# 4. Run the backend P0 suite
APP_ENV=staging vendor/bin/phpunit -c phpunit.staging.xml

# 5. Regenerate the driver contract matrix
APP_ENV=staging php scripts/audit/driver_contract_matrix.php \
  > docs/audit/driver_api_contract_matrix.csv
```

One-time setup, if Passport keys are absent:
`APP_ENV=staging php artisan passport:keys` (keys are gitignored).

The staging MySQL user needs `GRANT ALL` on the isolated schema.

## Fixtures

All fixtures use reserved ids in the **9001+ block**, so the seeder is
idempotent — re-running updates in place and never duplicates. The seeder
refuses to run unless the database name matches `/staging|test/i`, and
refuses outright in `production`.

| Role | Table | id | State |
|------|-------|----|-------|
| Super admin | `admins` | 9001 | role 9001, modules `["all"]` |
| Restricted admin | `admins` | 9002 | role 9002, modules `["order"]` |
| Shopper | `users` | 9001 | active |
| Vendor approved | `vendors` / `stores` | 9001 | `admin_approval_status=approved` |
| Vendor pending | `vendors` / `stores` | 9002 | `admin_approval_status=pending` |
| Vendor rejected | `vendors` / `stores` | 9003 | `admin_approval_status=rejected` |
| Driver online | `delivery_men` | 9001 | `active=1`, approved |
| Driver offline | `delivery_men` | 9002 | `active=0`, approved |
| Driver pending | `delivery_men` | 9003 | `application_status=pending` |
| Business client | `urban_goodz_business_clients` | 9001 | approved |
| Business owner | `urban_goodz_business_client_users` | 9001 | `role=owner` |
| Dispatcher | `urban_goodz_business_client_users` | 9002 | `role=dispatcher` |

Emails follow `staging.<role>@fixture.invalid`. The `.invalid` TLD is
reserved by RFC 2606 and can never resolve, so no fixture can email a real
person. The shared password is **not** in the repository.

## Autoloader caveat (important)

Because `vendor/` is shared with the main checkout, Composer's PSR-4 map
resolves `App\`, `Tests\` and `Database\` to the **main repo**. Any test run
with the stock `vendor/autoload.php` bootstrap silently exercises the main
repo's code against the wrong database.

`tests/staging_bootstrap.php` prepends a loader binding those namespaces to
this worktree, and `phpunit.staging.xml` uses it. **Do not** switch that
config back to `vendor/autoload.php`.

This was not theoretical: the vendor password-reset tests initially passed
the unfixed code because they were running the main repo's controller.

## Test results

`APP_ENV=staging vendor/bin/phpunit -c phpunit.staging.xml`

```
Tests: 24, Assertions: 87, Failures: 2, Errors: 0, Skipped: 0
```

Both failures are **genuine defects**, retained deliberately as reproducers:

1. `test_unapproved_stores_are_not_publicly_listed` — the public store
   listing returns vendors whose `admin_approval_status` is `pending` **and**
   `rejected`, each with `can_direct_checkout: true`.
2. `test_money_columns_have_sane_definitions` — `partial_payments.amount` and
   `subscription_billing_and_refund_histories.amount` are `double`.

## Documents

- `docs/audit/DRIVER_API_CONTRACT_MATRIX.md` + `driver_api_contract_matrix.csv`
- `docs/audit/ORDERS_BASELINE_PROPOSAL.md` (classification only, no migration)
- `docs/audit/DATABASE_RECOVERY_PATH_RESULTS.md`
- `docs/audit/pathc_migration_ledger.csv`, `pathc_ledger_decisions.csv`
