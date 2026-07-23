# Test run metadata

This file exists so the two raw artifacts in this directory (`phpunit-full.txt`, `new-test-run-3.txt`) can be tied to an exact code state instead of trusted on their own. Both artifacts were regenerated after the review-requested test-file corrections, immediately before commit.

| Field | Value |
|---|---|
| Git SHA (parent commit, working tree uncommitted on top) | `af5876e578cfc43720d0a99dbcd45a15b6de7848` |
| Branch | `e2e-certification-rebuild` |
| composer.lock SHA-256 | `35fc3298e843f85264381c86219115344e885f4ef67905bfaa1ae0479e82f385` |
| PHP version | 8.3.30 (cli, ZTS, Visual C++ 2019 x64) |
| PHPUnit version | 11.5.50 |
| Database | local MySQL, `urbangoodz_test` (schema drifted from current migrations — see inventory doc §6) |
| DB connection | supplied via env vars at invocation (`DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=`), **not** committed into `phpunit.xml` — see inventory doc §5 |
| Environment fingerprint | `CACHE_DRIVER=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `BCRYPT_ROUNDS=4` (all from the tracked `phpunit.xml`) |

## `phpunit-full.txt`

- Command: `DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= php vendor/bin/phpunit --no-coverage`
- Exit code: 2 (PHPUnit's exit code for "tests ran, but there were errors/failures")
- Result line: `Tests: 382, Assertions: 1027, Errors: 112, Failures: 7, PHPUnit Deprecations: 2, Skipped: 3.`
- Absolute local file-system paths in stack traces were replaced with the literal token `<repo>` before commit (the original paths embed the operator's real Windows account name, e.g. `C:\Users\<name>\...\AdminPanel_E2E_Rebuild`) — no other content was altered.

## `new-test-run-3.txt`

- Command: `DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= php vendor/bin/phpunit tests/Feature/UrbanGoodzPublicSurfaceValidationBoundaryTest.php --no-coverage --testdox`
- Exit code: 0
- Result line: `Tests: 22, Assertions: 73, PHPUnit Deprecations: 2, Skipped: 3.` (21 test methods; one data provider produces 2 executed cases — see inventory doc §6)
- Same path-scrubbing applied as above.

## Why `phpunit.xml` is not part of this commit

The working tree had local MySQL connection settings (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`) added directly to the tracked `phpunit.xml` to get the suite running against this operator's local `urbangoodz_test` database. That file was reverted to its committed state (`git checkout -- phpunit.xml`) before this commit — it is machine-specific and not portable to another developer's or CI's database. To reproduce these runs elsewhere, supply the same four variables as environment overrides at invocation time (as shown in the commands above), or add them to a local, gitignored `.env.testing` / shell profile — never commit real or default local credentials into `phpunit.xml` itself.
