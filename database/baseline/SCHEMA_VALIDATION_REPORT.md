# Candidate Schema Validation Report

**Artifact:** `database/baseline/urbangoodz_candidate_schema.sql`

**Status: CANDIDATE SCHEMA BASELINE — PRODUCTION PROVENANCE UNVERIFIED**

## Content validation (independently verified)

| Check | Result |
|---|---|
| `CREATE TABLE` statements | 242 |
| `INSERT` / `REPLACE` / `LOAD DATA` statements | 0 |
| Data rows included | 0 |
| `DEFINER=` clauses remaining | 0 |
| Credentials or secrets in file | none found |
| Absolute local filesystem paths | none found |

The secret scan matched only DDL identifiers (`oauth_access_tokens`,
`oauth_refresh_tokens`, `access_token_id`). These are table and column names, not
credential values.

## Import validation

The candidate schema was imported into the isolated staging database
`urbangoodz_isolated_staging_20260723` and verified:

- Table count after import: **242**
- Tables reporting rows: **0**
- Total data_length / index_length: 3.96 MB / 4.96 MB (empty InnoDB structures only)
- `orders` table: present
- `migrations` table: present and **empty** (0 rows — no Laravel migration recorded)

## Scope limits

This report validates **file content and import mechanics only**.

It does **not** establish that the schema matches current production. See
`SCHEMA_SOURCE_REPORT.md` for the provenance assessment.

**Export ready for isolated staging import:** Yes.
**Approved as production baseline:** No.
