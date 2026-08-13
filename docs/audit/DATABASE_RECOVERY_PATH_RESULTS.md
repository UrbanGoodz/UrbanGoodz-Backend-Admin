# Database Recovery — Three-Path Execution Results

**Date:** 2026-07-23
**Branch:** `claude-database-staging-recovery`
**Environment:** local MySQL 8.4.3, isolated databases only. Production never contacted.
**Deployed:** NO

All three paths use disposable local databases. The originally imported staging database
`urbangoodz_isolated_staging_20260723` was left **unchanged**.

| Path | Database |
|---|---|
| A — empty + migrations | `urbangoodz_migrations_only_20260723` |
| B — candidate baseline only | `urbangoodz_candidate_baseline_20260723` |
| C — baseline + later migrations | `urbangoodz_baseline_plus_migrations_20260723` |

The `isolated_user` account holds `USAGE` on `*.*` plus `ALL PRIVILEGES` on exactly these
four databases and can see no other schema.

---

## PATH A — empty database plus repository migrations

**Result: FAILED — reproduced the orders defect exactly. Not patched around.**

| Metric | Value |
|---|---|
| Migration files in repository | 337 |
| Migrations completed | **17** |
| Migrations recorded in `migrations` table | 17 (batch 1) |
| First failure | `2022_05_14_122133_add_dm_tips_column_to_orders_table` |
| Final table count | **16** |
| `orders` exists | **NO** |

Exact failure:

```
SQLSTATE[42S02]: Base table or view not found: 1146
Table 'urbangoodz_migrations_only_20260723.orders' doesn't exist
SQL: alter table `orders` add `dm_tips` double not null default '0'
```

The last migration to succeed was `2022_05_10_000000_create_customer_addresses_table`.
The very next migration is the first that touches `orders`, and it is an `ALTER`.

**Conclusion:** the repository cannot build a working schema from empty. There is no
`Schema::create('orders')` anywhere in the 337 migrations — confirmed by direct search.
All 22 orders-related migration files are `add_*_column_to_orders_table` ALTERs. The
create-orders migration is genuinely absent from version control.

---

## PATH B — candidate baseline only

**Result: PASSED.**

| Check | Value |
|---|---|
| Import exit code | 0 (clean, no errors) |
| Table count | **242** |
| Tables with rows > 0 | **0** |
| `orders` exists | **YES** |
| Order-related tables | 10 |
| Foreign keys | 84 |
| Indexes | 542 |
| Views / triggers / routines | 0 / 0 / 0 |
| `migrations` table rows | 0 |

Order-related tables present: `orders`, `order_details`, `order_transactions`,
`order_payments`, `order_taxes`, `order_references`, `order_cancel_reasons`,
`order_delivery_histories`, `order_anywhere_requests`,
`urban_goodz_order_anywhere_card_requests`.

Zero data rows, zero DEFINER clauses, no credentials in the file.

**Conclusion:** the candidate baseline imports cleanly and is structurally complete for
this schema generation. It is the only artifact currently capable of producing `orders`.

---

## PATH C — candidate baseline plus applicable later migrations

**Classification: COMPLETE. Execution: NOT RUN — blocked by disk.**

Migrations were **not** blindly executed against an already-current schema. A
deterministic classification was produced first by parsing every migration file and
comparing its intent against the actual imported baseline shape (242 tables, live
`information_schema` query).

### Classification of all 337 migrations

| Verdict | Count |
|---|---|
| ALREADY REPRESENTED | **281** |
| APPLICABLE AFTER BASELINE | **34** |
| DUPLICATE ALTER (partial overlap) | 6 |
| UNKNOWN (not statically decidable) | 16 |
| **CONFLICTS WITH BASELINE** | **0** |

Full per-migration detail: `docs/audit/migration_classification.csv`.

### The 34 applicable migrations date the baseline

Every migration classified APPLICABLE AFTER BASELINE is dated **2026-07-12 or later**.
Every migration dated 2026-07-09 or earlier is ALREADY REPRESENTED.

This places the candidate baseline snapshot between **2026-07-09 and 2026-07-12**. It is
an independent, evidence-based estimate of the schema's age — derived from schema shape,
not from any claim in a prior report.

The applicable set is dominated by feature tables added after that date: Fashion-Fit AI
workflow (10 tables), load sourcing (15 tables), AI workforce (`ai_agents`, `ai_tasks`,
`ai_approvals`, `ai_audit_events`, …), intake batching, route clustering, driver pricing
policies, and impersonation sessions.

### Known limits of the classifier — disclosed

The classification is a strong first pass, not a proof. Three limitations:

1. **Multi-table migrations** are evaluated against their first target table only. The 6
   DUPLICATE ALTER entries are mostly this case, not genuine conflicts.
2. **Raw SQL strings** inside migrations were occasionally parsed as column names,
   producing false "absent" entries (visible in
   `2026_07_09_020000_fix_route_packages_missing_portal_columns`).
3. **Index-only and FK-only migrations** carry no parsable column operation and land in
   UNKNOWN (7 of the 16). The remaining UNKNOWN entries are data-seeding or config
   migrations that alter no structure.

Each of the 34 applicable and 6 duplicate entries must be reviewed individually before
execution. The zero-conflict result is meaningful and encouraging, but it is a static
result and has not been confirmed by running the migrations.

### Why execution stopped

Free disk fell to **3.36 GB**, below the mandated 3.5 GB hard stop, immediately after
Path B. Execution halted at that point. Path C's import and migration run, and the
backend test suite, were **not executed**.

---

## Disk

| Checkpoint | Free |
|---|---|
| Session start | 4.66 GB |
| Before evidence commit | 4.00 GB |
| Before junction | 3.98 GB |
| After junction (zero cost) | 3.98 GB |
| Before migrations | 3.98 GB |
| After Path A | 3.92 GB |
| After Path B | **3.36 GB — HALT** |

Targeted read-only investigation of the drain:

- All four isolated databases together: **~17 MB** (8.5 + 8.5 + 0.3 MB).
- MySQL data directory total: ~157 MB, plus 100 MB InnoDB redo.
- Application log: 0 MB.

**The disk loss is not caused by this lane's work.** Free space continued falling while
the session was idle. The drain is an external process and needs separate investigation.
Nothing was deleted — no cleanup was performed without approval.

---

## Path C ledger vs. actual `migrations` batch distribution (cross-check)

Verified against the live Path C database `urbangoodz_pathc_20260723` after execution.
Every row in the `migrations` table is accounted for by a ledger decision or by a
module migration; there are no unsourced rows.

| Bucket | Count | Where it landed |
| --- | --- | --- |
| `RECORDED-NOT-EXECUTED` (represented, ledger-seeded) | 296 | batch 1, all 296 present, 0 elsewhere |
| `LEFT-PENDING-WILL-EXECUTE` (run by `artisan migrate`) | 41 | batch 2, all 41 present, 0 absent |
| Module migrations (`Modules/*/Database/Migrations`) | 25 | 23 in batch 1, 2 in batch 2 |
| **Total rows in `migrations`** | **362** | 296 + 41 + 25 = 362 ✓ |

Repository migration files on disk: 337 = 296 represented + 41 executed. ✓
Module migration files on disk: 25 = 25 recorded. ✓

Unsourced rows (in `migrations` but matching no file): **0**.
Ledger rows missing from the database: **0**.

Classifier verdict distribution in `docs/audit/pathc_migration_ledger.csv` (337 rows):
REPRESENTED 285, APPLICABLE 33, NO-OP (DATA) 16, EXECUTED 3.
The 3 formerly-UNRESOLVED rows now carry the `EXECUTED` verdict; zero UNRESOLVED remain.

Tables in the executed Path C database: **306** (candidate baseline 242 + 64 created by
the 43 executed migrations).

### Credential handling

The ledger scripts take their database login from the environment
(`PATHC_DB_USER` / `PATHC_DB_PASS`, optionally `PATHC_DB_HOST` / `PATHC_DB_PORT`)
via `pathc_pdo()` in `scripts/audit/pathc_ledger_lib.php`. No login is hardcoded and
none is committed. `.env.staging` holds the staging credentials and is excluded by the
`.env.*` rule in `.gitignore`.

Run them as:

```bash
export PATHC_DB_USER=... PATHC_DB_PASS=...
php scripts/audit/pathc_ledger.php urbangoodz_pathc_20260723 --write-ledger
```
