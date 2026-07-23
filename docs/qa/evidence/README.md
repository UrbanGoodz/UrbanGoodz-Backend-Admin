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
| `full-suite-patched.junit.xml` | Per-test results for the whole Feature+Unit suite |
| `full-suite-patched.summary.txt` | Inventory of the 119 pre-existing failing test IDs |
| `staging-db-blocker.txt` | Reproduction of why the Playwright suite cannot run here |

## Results

Focused suite: **33 tests, 134 assertions, 0 errors, 0 failures, 0 skipped.**

Full suite: **388 tests, 112 errors, 7 failures** — unchanged from the
pre-patch baseline measured in the same environment. These are pre-existing
and unrelated to this work: Laravel Passport has no OAuth keys generated
(`League\OAuth2\Server\CryptKey: Invalid key supplied`) and several feature
areas reference tables the local database does not have. The per-test IDs are
in the JUnit XML for anyone wanting to diff them against another run.

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
