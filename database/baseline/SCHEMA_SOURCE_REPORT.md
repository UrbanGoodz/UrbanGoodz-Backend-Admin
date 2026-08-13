# Candidate Schema Source Report

**Artifact:** `database/baseline/urbangoodz_candidate_schema.sql`

**Status: CANDIDATE SCHEMA BASELINE — PRODUCTION PROVENANCE UNVERIFIED**

## Source of record

- **Dump header `Database:`** urbakkej_urbangoodzdelivery
- **Dump header `Host:`** localhost
- **Dump tool / server version:** MySQL dump 10.13, Distrib 8.4.3, Win64
- **Extraction method:** mysqldump against a **local** MySQL instance

## Provenance assessment

The dump header records `Host: localhost` and server version 8.4.3, which matches the
local MySQL instance on this workstation. The schema was therefore taken from a **local
database named `urbakkej_urbangoodzdelivery`**, not from a verified read-only export of
the live production server.

An earlier revision of this report described the source as a "production copy". That
description was not supported by the dump metadata and has been retracted.

**What is proven:**
- The file is a structure-only dump of a local database carrying the production database name.
- It contains 242 table definitions and zero data rows.

**What is NOT proven:**
- That the local source database matches current production schema state.
- The age of the local copy, or which migrations had been applied when it was dumped.
- Whether production has since diverged (added, dropped, or altered columns).

## Extracted objects

| Object | Count |
|---|---|
| Tables | 242 |
| Views | 0 |
| Triggers | 0 |
| Routines | 0 |
| Events | 0 |
| Data rows (INSERT statements) | 0 |

## Required before this baseline may be treated as authoritative

1. A fresh read-only schema export from the live production server.
2. A table-by-table and column-by-column diff against this candidate.
3. Reconciliation of the `orders` definition against all repository migrations and
   application code (see `docs/audit/orders_table_baseline.txt`).
4. Independent review sign-off.

Until all four are complete this file must be referred to as the **CANDIDATE SCHEMA
BASELINE** and must not be used to generate a production migration.
