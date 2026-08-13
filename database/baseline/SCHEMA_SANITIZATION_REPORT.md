# Candidate Schema Sanitization Report

**Artifact:** `database/baseline/urbangoodz_candidate_schema.sql`
**Sanitizer:** `scripts/audit/sanitize_schema.js`
**Input:** raw local dump (excluded from version control — see `.gitignore`)

**Status: CANDIDATE SCHEMA BASELINE — PRODUCTION PROVENANCE UNVERIFIED**

## Transformations applied

| Transformation | Count |
|---|---|
| DEFINER clauses removed | 0 |
| AUTO_INCREMENT resets applied | 75 |
| Database references renamed | 0 |
| Sensitive comments redacted | 1 |

DEFINER count is 0 because the source dump contained no views, triggers, routines, or
events — there were no DEFINER clauses to strip.

## Post-sanitization verification

Independently re-scanned after sanitization:

- `DEFINER=` occurrences remaining: **0**
- Data statements (`INSERT` / `REPLACE` / `LOAD DATA`): **0**
- Credentials, tokens, or keys: none found
- Absolute local filesystem paths: none found

## Excluded from version control

The raw pre-sanitization export (`database/baseline/raw_schema.sql`) is deliberately
**not committed** and is listed in `.gitignore`. Only the sanitized candidate schema is
tracked.

Sanitization addresses secret and definer hygiene only. It does **not** confer
production provenance.
