# SESSION — Admin Auth Recovery — Lane Freeze

**Status:** FROZEN pending external inputs
**Date:** 2026-07-23
**Lane:** Admin authentication / authorization recovery
**Deployment:** NONE. Nothing from this lane has been deployed.

---

## 1. Accepted correction commit

| Field | Value |
|---|---|
| **Accepted SHA (full)** | `6937e5d5c8d7bbf0c6405eda8a6c1d0e922033c9` |
| Local branch | `admin-auth-recovery` |
| Remote branch | `origin/adminpanel-v39-backend-sprint` |
| Pushed to origin | **YES** — confirmed by `git ls-remote --heads origin`, which returns this exact SHA for `refs/heads/adminpanel-v39-backend-sprint` |
| Force-push used | **NO** |
| History rewritten | **NO** |
| Working tree | Clean except `resources/lang/en/messages.php` (see §7) |

### SHA correction — recorded deliberately

The acceptance instruction supplied `d7bbf0c6405eda8a6c1d0e922033c9` (30 chars).
That string **does not resolve** — `git rev-parse` returns
`fatal: Needed a single revision`. It is the **last 30 characters** of the real
SHA; the 10-character prefix `6937e5d5c8` was dropped. Git resolves prefixes,
never suffixes.

The accepted commit is therefore recorded here as the full 40-character
`6937e5d5c8d7bbf0c6405eda8a6c1d0e922033c9`. Any downstream deploy, tag, or
audit step must use that value, not the truncated form.

### Commit lineage of the lane

```
af5876e  pre-patch baseline (parent of the series)
 └─ 4df8e55  fail-closed CAPTCHA, module authorization, activation, cache
    └─ 81960c1  CAPTCHA score/action gaps, dashboard authz extraction
       └─ 3b8d2e2  generic login error, urban_goodz_view route test, artifacts
          └─ a7f8b96  BASE_URL guard, isolated snapshots, real POST assertions
             └─ 6937e5d  ACCEPTED — sanitized evidence, identity proof, fixture attestation
```

---

## 2. Test position

| Metric | Value |
|---|---|
| Total tests (Feature+Unit, patched) | **388** |
| Total tests at pre-patch baseline `af5876e` | 360 |
| **Newly added tests** | **28** (delta is exactly the focused-suite additions) |
| Focused suite | 33 tests, 134 assertions, 0 failures, 0 skipped |
| Failing identities, baseline | 119 |
| Failing identities, patched | 119 |
| **Identical failing-identity set** | **YES** |
| **Regressions introduced** | **0** |
| Tests fixed by the patch | 0 |

This is an **identity** comparison, not a count comparison. Both runs were
executed back-to-back on the same machine with the same `vendor/`, `.env`, and
`phpunit.xml`; failing test IDs were diffed, not tallies.

Root-cause taxonomy of the 119 shared pre-existing failures:

| Category | Count | errors | failures |
|---|---:|---:|---:|
| missing DB column | 93 | 92 | 1 |
| missing class / undefined method | 18 | 18 | 0 |
| Passport OAuth keys absent | 3 | 0 | 3 |
| foreign-key constraint | 2 | 2 | 0 |
| other (stale assertions / schema) | 3 | 0 | 3 |

Reconciles exactly to 112 errors + 7 failures. All are environmental.

Evidence: `docs/qa/evidence/` — `full-suite-baseline.junit.xml`,
`full-suite-patched.junit.xml`, `baseline-vs-patched.txt`,
`focused-admin-login-recovery.junit.xml`, plus `README.md` with exact commands.
All artifacts are path-sanitized via `scripts/sanitize-test-evidence.php`.

---

## 3. Staging Playwright — BLOCKED

**Status: not executed. No browser-level certification exists for this lane.**

The Browser suite cannot run from this repository. Reproduction is committed at
`docs/qa/evidence/staging-db-blocker.txt`:

- **21 migrations ALTER the `orders` table. No migration CREATES it.**
- No full schema dump is committed — only partial seed fragments under
  `database/partial/`.
- A clean `php artisan migrate --force` against an empty database fails at
  `2022_05_14_122133_add_dm_tips_column_to_orders_table` with
  `SQLSTATE[42S02] ... Table 'orders' doesn't exist`.

### Missing authoritative `orders` baseline

This is the blocking root cause. The repository is **not self-sufficient**: it
assumes a pre-existing database whose provenance is outside Git history. Until
an authoritative, sanitized schema is supplied, no environment can be rebuilt
from source, and therefore no browser-level test run is reproducible.

Consequently these remain **source-only, unexecuted** claims:

- authorized Admin reaches the `module:urban_goodz_view`-protected route
- restricted Admin is denied it
- dashboard render, session refresh, logout, post-logout denial
- CAPTCHA behaviour at the browser layer

---

## 4. Fixture-account requirements

The browser suite is a certification gate: it **fails, never skips**, when these
are absent.

| Env var | Requirement |
|---|---|
| `ADMIN_TEST_EMAIL` / `ADMIN_TEST_PASSWORD` | Admin with `role_id != 1` whose `admin_roles.modules` **includes** `urban_goodz_view` |
| `ADMIN_RESTRICTED_TEST_EMAIL` / `ADMIN_RESTRICTED_TEST_PASSWORD` | Admin with `role_id != 1` whose role is **otherwise identical** but **excludes** `urban_goodz_view` |
| `BASE_URL` | Required. No default. Production hostname aborts unless `ALLOW_PRODUCTION_BASE_URL=true` |

Neither account may be the primary Admin: `role_id == 1` short-circuits
`Helpers::module_permission_check()` and would certify nothing.

**Both accounts must be attested before the suite will certify authorization.**
Run on the staging host:

```
php scripts/verify-admin-role-fixture.php --authorized=<email> --restricted=<email>
```

This is SELECT-only. It reads authoritative `admin_roles.modules`, asserts both
accounts are non-primary and their module arrays differ by **exactly**
`urban_goodz_view`, and writes
`docs/qa/evidence/role-fixture-verification.json` containing truncated SHA-256
account references — never plaintext emails. The Playwright preflight requires
that attestation and verifies it describes the accounts actually under test.

Target environment must also run `APP_MODE=dev` so the custom CAPTCHA phrase is
server-side pre-filled. **This is a staging-only mechanism.**

---

## 5. Deployment status

**NOT DEPLOYED. NO DEPLOYMENT AUTHORIZED.**

- No production file was modified, backed up, or replaced.
- No cache operation was run against production.
- No cPanel or document-root change was made.
- Live SHA remains unverified from this lane; no production connection was made.
- Corrected cPanel document root, `public/.htaccess` from `ebb7fbb`, public
  website routing, Business Portal, and production DB config were all preserved
  untouched.

Hard gate stands: **no deployment without independent reviewer approval.**

---

## 6. Deferred issue — repository privacy hygiene

Commits `a7f8b96` and earlier retain the operator's absolute Windows path
(`C:\Users\<user>\...`) inside committed JUnit artifacts — roughly 2,200 lines
in the full-suite file.

- **Classification:** privacy hygiene. **Not** a credential exposure. No
  password, key, token, or session material is present.
- **Forward state:** sanitized from `6937e5d` onward; scanner enforces it.
- **Action:** DEFERRED by owner decision. **Do not rewrite history. Do not
  force-push.**

Note: PHPUnit re-writes absolute paths on every run, so
`scripts/sanitize-test-evidence.php` must be re-run after regenerating any
artifact. `--check` scans without writing.

---

## 7. Known working-tree condition

`resources/lang/en/messages.php` shows as persistently modified. This is **not**
a stray manual edit.

`translate()` in `app/helpers.php` (lines 34–38) auto-appends any missing
translation key and rewrites the entire file via `var_export` **at runtime**.
Any page render encountering a new key mutates this source file. This explains
the recurring dirty-tree observation across all review rounds.

Operational consequence: the application writes to its own source tree in
production. Flagged for separate review; **not** addressed in this lane.

---

## 8. LANE FREEZE

This lane is **FROZEN**. No further authentication or authorization changes are
to be made.

Unfreezes only when **Antigravity 2** supplies all five:

1. Sanitized authoritative schema (must include the `orders` baseline)
2. Isolated staging database
3. Staging URL
4. Admin fixture account (`urban_goodz_view` granted, non-primary)
5. Restricted-role fixture account (`urban_goodz_view` withheld, non-primary,
   otherwise identical role)

On receipt, and only then:

1. Run `scripts/verify-admin-role-fixture.php` on staging; retain attestation.
2. Run the Playwright suite **against staging only** — never production.
3. Sanitize all artifacts.
4. Submit the complete result set for independent review.

### Standing constraints

- Production is **never** the default Playwright target.
- Missing credentials, test data, controls, or assertions **fail the run** —
  never a silent skip.
- No deployment without reviewer approval.
