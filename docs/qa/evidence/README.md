# Admin auth recovery — test evidence

Machine-readable artifacts for the admin-auth-recovery security patch series
(`4df8e55` → `81960c1` → `3b8d2e2` → this commit). Added because prior review
rounds could not independently verify reported test tallies.

## Exact commands

Environment prerequisites (this checkout ships no `vendor/`, `.env`, or
`storage/framework/`):

```
composer install --no-interaction --no-progress --prefer-dist -o
cp .env.example .env
mkdir -p storage/framework/{views,cache/data,sessions,testing} storage/logs storage/app/public bootstrap/cache
php artisan key:generate
```

Focused suite:

```
php vendor/bin/phpunit tests/Feature/AdminLoginRecoveryRegressionTest.php \
  --log-junit docs/qa/evidence/focused-admin-login-recovery.junit.xml
```

Full suite:

```
php vendor/bin/phpunit --testsuite Feature,Unit \
  --log-junit docs/qa/evidence/full-suite-patched.junit.xml
```

PHPUnit reads `phpunit.xml`, which pins `APP_ENV=testing`, `CACHE_DRIVER=array`,
`SESSION_DRIVER=array`, and a fixed `APP_KEY`. The focused suite builds its own
in-memory SQLite schema per test and does not touch any MySQL database.

## Artifacts

| File | What it shows |
|---|---|
| `focused-admin-login-recovery.junit.xml` | Per-test results for the focused suite |
| `full-suite-patched.junit.xml` | Per-test results, whole Feature+Unit suite, patched |
| `full-suite-baseline.junit.xml` | Same suite at pre-patch `af5876e` |
| `baseline-vs-patched.txt` | Failing-identity diff + root-cause taxonomy |
| `full-suite-patched.summary.txt` | Inventory of the 119 pre-existing failing test IDs |
| `staging-db-blocker.txt` | Reproduction of why the Playwright suite cannot run here |
| `role-fixture-verification.json` | *(generated on staging)* attestation that the two Admin fixtures are non-primary and differ by exactly `urban_goodz_view` |
| `sqlite-migration-tables-created.txt` / `sqlite-migration-tables-modified.txt` | Table-level diff captured while backfilling the missing base-schema migrations (see below) |
| `orders-table-schema-dump.txt` | `SHOW CREATE TABLE orders` captured from a working MySQL install, used as the source of truth when writing `database/migrations/2022_05_10_000001_create_orders_table.php` |
| `clean-migrate-baseline.log` | `migrate --force` against an empty database, still pre-fix: confirms the same `orders` gap described in `staging-db-blocker.txt` (captured before the `2022_05_10_*` migrations below existed) |

### Missing base-schema migrations (`orders` gap)

`staging-db-blocker.txt` documents that this checkout had 21 migrations that
`ALTER` the `orders` table but none that `CREATE` it (and 25 other base
tables in the same situation — `stores`, `items`, `admins`, `delivery_men`,
etc.). `database/migrations/2022_05_10_000001_*` through
`2022_05_10_000026_*` add the missing `CREATE TABLE` migrations, timestamped
before the first `ALTER` migration for each table, each guarded with
`Schema::hasTable()` so they no-op against a database that already has the
table. Column definitions were reconstructed from
`orders-table-schema-dump.txt` and the existing `ALTER` migrations. This has
not been re-verified end-to-end with a live `php artisan migrate` from this
pass — `clean-migrate-baseline.log` predates the new files — so treat the
gap as addressed but not freshly re-proven.

All artifacts are path-sanitized. `scripts/sanitize-test-evidence.php` rewrites
absolute paths to `[repo]` and scans for Windows/Unix home paths, private keys,
AWS keys, bearer tokens, and password assignments. Re-run it after regenerating
anything; `--check` scans without writing. PHPUnit re-introduces absolute paths
on every run, so sanitization is a required post-processing step, not one-time.

## Results

Focused suite: **33 tests, 134 assertions, 0 errors, 0 failures, 0 skipped.**

Full suite: **388 tests, 112 errors, 7 failures.**

Pre-patch baseline (`af5876e`), run back-to-back on the same machine with the
same `vendor/`, `.env`, and `phpunit.xml`: **360 tests, 112 errors, 7
failures.** The 28-test delta is exactly the tests added to the focused suite
(33 − 5 original).

Crucially, this is an **identity** comparison, not just a count comparison —
see `baseline-vs-patched.txt`:

```
baseline failing cases: 119
patched  failing cases: 119
identical identity set: YES
REGRESSIONS introduced by the patch: 0
FIXED by the patch:                  0
```

### Root-cause taxonomy of the 119 shared failures

| Category | Count | errors | failures |
|---|---:|---:|---:|
| missing DB column | 93 | 92 | 1 |
| missing class / undefined method | 18 | 18 | 0 |
| Passport OAuth keys absent | 3 | 0 | 3 |
| foreign-key constraint | 2 | 2 | 0 |
| other (stale assertions / schema) | 3 | 0 | 3 |

Reconciles exactly to 112 errors + 7 failures. All 119 are environmental —
this checkout cannot build its own schema (see below) — and every one of them
fails identically before and after the patch.

Note on assertion count: the focused suite went from 137 assertions to 134
when `test_unknown_email_and_wrong_password_produce_identical_responses` was
rewritten. One deep `assertSame()` over a full outcome snapshot replaced
several shallow per-field assertions — fewer assertions, strictly stronger
coverage.

## Not covered here

`tests/Browser/*` (Playwright) has **not** been executed. See
`staging-db-blocker.txt`: this repository has 21 migrations that ALTER the
`orders` table but no migration that CREATES it, and no full schema dump is
committed — only partial seed fragments under `database/partial/`. A clean
`php artisan migrate` therefore fails at
`2022_05_14_122133_add_dm_tips_column_to_orders_table`. Running the browser
suite requires a staging environment restored from a database snapshot that
lives outside this Git history, plus the two fixture Admin accounts described
at the top of `tests/Browser/AdminLoginTest.spec.js`.

The `base-url-guard` production-host abort *was* verified executably — every
bypass variant (trailing dot, double dot, case variants) is covered by
`tests/Browser/base-url-guard.spec.js` and was additionally confirmed by
direct Node execution of the pure guard function.
