# DCP CHECKPOINT — DATABASE AND STAGING RECOVERY

**Date:** 2026-07-23
**Lane:** Database and staging recovery
**Branch:** `claude-database-staging-recovery`
**Base:** `codex-full-platform-audit-sprint` @ `432061b7142fd0283dda77358a05ad749abba117`
**Worktree:** `AdminPanel_Codex_Platform_Audit`
**Deployed:** NO

---

## 1. Purpose

Preserve the database recovery evidence that existed only as untracked files in a single
working directory, and correct provenance claims that were not supported by source
evidence.

---

## 2. Independently verified state at checkpoint

### Git

| Item | Value |
|---|---|
| Main repository | `AdminPanel_Update_V39` |
| Main branch / SHA | `adminpanel-v39-backend-sprint` @ `af5876e` (clean, 6 behind origin) |
| Antigravity worktree branch | `antigravity-audit-correction` @ `af5876e` |
| Antigravity commits | **zero** — reflog shows only `branch: Created from HEAD` |
| Deleted tracked files (all worktrees) | **0** |

The Antigravity audit branch head is identical to the main branch head. No audit
document was ever committed on it, and it was never pushed. All recovery artifacts
existed as untracked files in the `AdminPanel_Codex_Platform_Audit` worktree.

### Isolated staging database

| Item | Value |
|---|---|
| Database | `urbangoodz_isolated_staging_20260723` |
| Account | `isolated_user@localhost` |
| Login | verified working |
| Tables | 242 |
| Tables with rows | 0 |
| `orders` present | yes |
| `migrations` table rows | **0 — no Laravel migration has ever run** |

Grants verified exactly:

```
GRANT USAGE ON *.* TO `isolated_user`@`localhost`
GRANT ALL PRIVILEGES ON `urbangoodz_isolated_staging_20260723`.* TO `isolated_user`@`localhost`
```

`SHOW DATABASES` as that account returns only `information_schema`,
`performance_schema`, and the isolated database. **No production visibility.**

Credentials live only in the worktree's `.env.testing`, which is ignored by
`.gitignore:2 (.env.*)` and is not tracked. No credential is in version control.

---

## 3. Corrections to prior claims

| Prior claim | Verified finding |
|---|---|
| Audit documents were committed | **False.** Zero commits on the audit branch; all artifacts untracked. |
| Composer dependencies are missing | **False for the main repository.** `vendor/` there is complete: autoload bootstraps, Laravel and PHPUnit classes resolve, 207/207 locked packages installed, 0 missing. True only for audit worktrees, where `composer install` was never run — nothing was deleted. |
| Schema source is a production copy | **Unsupported.** Dump header reads `Host: localhost`, server 8.4.3, matching the local MySQL instance. Source is a **local** database named `urbakkej_urbangoodzdelivery`. |
| `composer.lock` diverged between worktrees | **Withdrawn.** Byte sizes differ only due to `core.autocrlf=true`; `git hash-object` returns `8d03498…` in both, identical to the HEAD blob. |

---

## 4. Artifact renamed

`database/baseline/urbangoodz_authoritative_schema.sql`
→ `database/baseline/urbangoodz_candidate_schema.sql`

All documentation now refers to it as the
**CANDIDATE SCHEMA BASELINE — PRODUCTION PROVENANCE UNVERIFIED**.

---

## 5. Committed vs excluded

**Committed (safe recovery evidence):**

- `database/baseline/urbangoodz_candidate_schema.sql` — sanitized, 242 CREATE TABLE, 0 data rows
- `database/baseline/SCHEMA_SOURCE_REPORT.md` — rewritten with honest provenance
- `database/baseline/SCHEMA_SANITIZATION_REPORT.md` — rewritten
- `database/baseline/SCHEMA_VALIDATION_REPORT.md` — rewritten
- `docs/audit/orders_table_baseline.txt` — verbatim `SHOW CREATE TABLE orders` evidence
- `docs/audit/CANONICAL_DASHBOARD_DATA_MAP.md` — marked PRELIMINARY — NOT APPROVED
- `scripts/audit/sanitize_schema.js` — sanitizer used to produce the candidate
- `.gitignore` — excludes the raw export
- this checkpoint

**Deliberately excluded:**

- `database/baseline/raw_schema.sql` — raw unsanitized export, now gitignored
- `.env.testing` — ignored, contains the working staging credential
- Any credential, password, or production data
- Absolute local user paths

**Scans run against the exact staged content:** secret scan and local-path scan.
Only false positives found (`oauth_access_tokens`, `oauth_refresh_tokens`,
`access_token_id` DDL identifiers, and the sanitizer's own `DEFINER` regex literal).

---

## 6. Open blockers carried forward

1. **Free disk critically low** (~4.0 GB, shrinking). No Composer install, browser
   install, APK build, or coverage run is permitted. Hard stop at 3.5 GB.
2. **`orders` baseline gap.** 337 repository migrations contain **zero**
   `Schema::create('orders')`; all 22 orders-related migrations are `ALTER`-type. An
   empty database plus migrations cannot produce `orders`.
3. **Production provenance unverified.** No fresh read-only production schema export has
   been obtained.
4. **`AdminPanel_Update_V39\.env.testing` is stale and broken** — points at
   `urbangoodz_test` as `root` with a password that fails authentication (ERROR 1045).
   Left untouched by direction; the main repository is not the certification environment.

---

## 7. Execution phase — zero-disk vendor junction

Composer was **not** run in any form. Dependencies were reused from the main repository
through a read-only Windows directory junction.

| Item | Value |
|---|---|
| Junction | `<GITHUB_ROOT>\AdminPanel_Codex_Platform_Audit\vendor` |
| Target | `<GITHUB_ROOT>\AdminPanel_Update_V39\vendor` |
| Type | Directory / ReparsePoint / Junction |

`<GITHUB_ROOT>` is the local GitHub checkout directory. The literal absolute path is
deliberately not recorded here — it contains the workstation user name. Both worktrees
live side by side under the same root, so the junction is reproducible from that fact
alone.
| Disk cost | **0 bytes** (3.98 GB free before and after) |
| Composer commands run | **NONE** |

`composer.lock` equivalence proven before linking:

- Working-tree bytes differ (552,383 vs 567,320) purely from `core.autocrlf`.
- CRLF delta is 14,937 bytes against exactly 14,937 lines.
- After CRLF→LF normalization both hash to `B53EC365…`.
- `git hash-object` returns `8d03498…` for both, identical to the HEAD blob.

Bootstrap verified through the junction:

| Check | Result |
|---|---|
| `vendor/autoload.php` | AUTOLOAD_OK |
| Laravel application class | resolves |
| PHPUnit class | resolves |
| Packages installed vs lock | 207 / 207, 0 missing, 0 absent dirs |
| `php artisan --version` | Laravel Framework 12.50.0 |
| `vendor/phpunit` version | PHPUnit 11.5.50 |
| `php artisan migrate:status --env=testing` | connects; all migrations Pending |

Artisan initially failed with "Please provide a valid cache path". Cause: the gitignored
`storage/framework/*` and `bootstrap/cache` directories do not exist in a fresh worktree.
Created as empty directories; no tracked file touched.

## 8. Execution phase — three database paths

Full detail: `docs/audit/DATABASE_RECOVERY_PATH_RESULTS.md`.

| Path | Database | Result |
|---|---|---|
| A — empty + migrations | `urbangoodz_migrations_only_20260723` | **FAILED as predicted** — 17/337, 16 tables, no `orders` |
| B — candidate baseline | `urbangoodz_candidate_baseline_20260723` | **PASSED** — 242 tables, 0 rows, 84 FKs, 542 indexes |
| C — baseline + migrations | `urbangoodz_baseline_plus_migrations_20260723` | **classified, NOT executed** (disk halt) |

Path A first failure, verbatim:

```
SQLSTATE[42S02]: Base table or view not found: 1146
Table 'urbangoodz_migrations_only_20260723.orders' doesn't exist
SQL: alter table `orders` add `dm_tips` double not null default '0'
```

Path C classification of all 337 migrations against the imported baseline:
**281 ALREADY REPRESENTED, 34 APPLICABLE AFTER BASELINE, 6 DUPLICATE ALTER,
16 UNKNOWN, 0 CONFLICTS.** Per-migration detail in
`docs/audit/migration_classification.csv`.

Every APPLICABLE migration is dated 2026-07-12 or later; every migration dated
2026-07-09 or earlier is already represented. This dates the baseline snapshot to
**between 2026-07-09 and 2026-07-12** on schema evidence alone.

## 9. Orders baseline reconciliation

Full detail: `docs/audit/ORDERS_BASELINE_RECONCILIATION.md`.

Candidate `orders` = 79 columns.

- Columns the 22 orders ALTER migrations would add: 29 — **0 absent from baseline**.
- `Order` model references: 33 — 1 absent, `details_count`, which is an Eloquent
  `withCount` aggregate alias and not a column. **Not a schema gap.**
- 32 baseline columns trace to no migration; these define the absent original
  `create_orders` migration and are enumerated in the reconciliation document.

No orders schema was invented. No executable migration is proposed, because three of the
four required gates (production comparison, passing tests, independent review) are unmet.

`adjusment` is misspelled in the live schema; any reconstruction must preserve it.

## 10. HALT — disk threshold breached

Free disk fell to **3.36 GB**, below the mandated 3.5 GB hard stop, immediately after
Path B. Execution halted there.

**Not executed:** Path C import and migration run, backend tests, authorization tests,
wallet/ledger/order tests, fixture account creation, production schema comparison.

**No test was run, so nothing is certified.** No certification claim is made.

Targeted read-only investigation of the drain:

| Source | Size |
|---|---|
| All four isolated databases combined | ~17 MB |
| MySQL data directory | ~157 MB |
| InnoDB redo | 100 MB |
| Application log | 0 MB |

The drain is **not** attributable to this lane. Free space kept falling while the session
was idle (3.36 → 3.31 GB). An external process is consuming disk and needs separate
investigation. Nothing was deleted; no cleanup was performed without approval.

## 11. Additional finding — local MySQL root has no password

While establishing database-creation access, `root@localhost` was found to authenticate
with an **empty password**. The stale `root` credential in
`AdminPanel_Update_V39\.env.testing` fails (ERROR 1045), but passwordless root succeeds.

Any local process can therefore read and drop every local database, including the
isolated staging databases. This was not changed — altering local MySQL authentication is
outside this lane and would break other tooling. **Flagged for a decision.**

## 12. Constraints honored

- No deployment.
- No production data modified; no production database contacted.
- No force-push, no history rewrite, no `reset --hard`, no `clean -fd`, no `restore .`.
- No credential printed or committed.
- No tracked source file deleted.
- Main repository untouched: branch not switched, not pulled, not reset, `vendor/` not modified.
