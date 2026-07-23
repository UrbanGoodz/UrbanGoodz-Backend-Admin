# Test run metadata

This file exists so the two raw artifacts in this directory (`phpunit-full.txt`, `new-test-run-3.txt`) can be tied to an exact code state instead of trusted on their own. Both artifacts were regenerated a second time after an independent review of the first commit (`dc738df980b5f2d72e38d034086c44fb3c801def`) found leftover path fragments and two weak assertions; this metadata describes that final, second regeneration.

| Field | Value |
|---|---|
| Git SHA (parent commit, working tree uncommitted on top of it at run time) | `af5876e578cfc43720d0a99dbcd45a15b6de7848` |
| Prior commit on this branch (superseded) | `dc738df980b5f2d72e38d034086c44fb3c801def` |
| Branch | `e2e-certification-rebuild` |
| Test file SHA-256 (`tests/Feature/UrbanGoodzPublicSurfaceValidationBoundaryTest.php`, as committed) | `a925ab2d66471ace461c413f3bece6bb65f83543a88b9579a5683d935a3c42c8` |
| composer.lock SHA-256 | `35fc3298e843f85264381c86219115344e885f4ef67905bfaa1ae0479e82f385` |
| PHP version | 8.3.30 (cli, ZTS, Visual C++ 2019 x64) |
| PHPUnit version | 11.5.50 |
| Shell | Git Bash (POSIX `sh`) — the `VAR=value command` env-var-prefix syntax below requires this; it is not valid PowerShell or cmd.exe syntax. In PowerShell, set each variable with `$env:VAR = 'value'` first instead. |
| Database | local MySQL, `urbangoodz_test` (schema drifted from current migrations — see inventory doc §6) |
| DB connection | supplied via env vars at invocation (`DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=`), **not** committed into `phpunit.xml` — see inventory doc §5 |
| Environment fingerprint | `CACHE_DRIVER=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `BCRYPT_ROUNDS=4` (all from the tracked `phpunit.xml`) |

## `phpunit-full.txt`

- Command: `DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= php vendor/bin/phpunit --no-coverage`
- Exit code: 2 (PHPUnit's exit code for "tests ran, but there were errors/failures")
- Result line: `Tests: 382, Assertions: 1027, Errors: 112, Failures: 7, PHPUnit Deprecations: 2, Skipped: 3.`
- Absolute local file-system paths in stack traces were replaced with the literal token `<repo>` before commit (the original paths embed the operator's real Windows account name, e.g. `C:\Users\<name>\...\AdminPanel_E2E_Rebuild`) using the two-pass method below — no other content was altered.

## `new-test-run-3.txt`

- Command: `DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= php vendor/bin/phpunit tests/Feature/UrbanGoodzPublicSurfaceValidationBoundaryTest.php --no-coverage --testdox`
- Exit code: 0
- Result line: `Tests: 22, Assertions: 73, PHPUnit Deprecations: 2, Skipped: 3.` (21 test methods; one data provider produces 2 executed cases — see inventory doc §6)
- Same path-scrubbing applied as above.

## Path-scrubbing method (and its limit)

The first commit's scrub did a single literal `str_replace()` of the exact full local path. That missed cases where PHP/PHPUnit's own stack-trace formatter had already truncated a long string argument mid-path (a quoted argument preview cut short by PHP's own formatter, mid-way through the path) — the truncated fragment no longer matched the full-path needle. This regeneration scrubs in two passes: (1) the same full-path literal replace (caught 3,370 occurrences in `phpunit-full.txt`, 1 in `new-test-run-3.txt`), then (2) a regex pass matching any remaining `C:\Users\D...` fragment regardless of where it was cut off (0 additional matches in this particular regeneration — the literal pass alone happened to catch everything this time, since PHP's argument-truncation points weren't hit in the same places as the prior run; the second pass is kept as a standing safeguard regardless). Both files were re-verified at zero remaining matches across five independent patterns (`D'Andre`, `C:\Users`, short-form `D'ANDR`, `D_Andre`, `Andre Good`) before this commit. **This does not retroactively clean the prior commit (`dc738df`) already on `origin/e2e-certification-rebuild`** — those 5 fragments remain in that commit's history; removing them would require a force-push history rewrite, which was not performed without explicit authorization since it rewrites shared branch history.

## Why `phpunit.xml` is not part of this commit

The working tree had local MySQL connection settings (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`) added directly to the tracked `phpunit.xml` to get the suite running against this operator's local `urbangoodz_test` database. That file was reverted to its committed state (`git checkout -- phpunit.xml`) before this commit — it is machine-specific and not portable to another developer's or CI's database. To reproduce these runs elsewhere, supply the same four variables as environment overrides at invocation time (as shown in the commands above), or add them to a local, gitignored `.env.testing` / shell profile — never commit real or default local credentials into `phpunit.xml` itself.
