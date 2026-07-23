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

## 7. Constraints honored

- No deployment.
- No production data modified; no production database contacted.
- No force-push, no history rewrite, no `reset --hard`, no `clean -fd`, no `restore .`.
- No credential printed or committed.
- No tracked source file deleted.
- Main repository untouched: branch not switched, not pulled, not reset, `vendor/` not modified.
