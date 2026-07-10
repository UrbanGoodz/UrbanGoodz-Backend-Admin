# P4A CATEGORY BACKFILL READINESS REPORT
## Urban Goodz — Session 1 / Data Import
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Status:** READINESS ONLY — no backfill executed.

---

## 1. Pending category_ids rows
- Total staged rows: **431**
- Rows with `category_ids = []` (pending): **384**
- Rows already resolved during P3B import: **47**

## 2. Data shape used for matching
Each staged row stores `tags` = `[<business_category>, <subcategory_token>, ...]`.
Example: `["Beauty / Personal Care","cosmetics",...]`. The **second tag**
is the subcategory token used for matching against granular categories of
the row's `module_id`.

## 3. Matching rule (proposed, safe)
Assign `category_ids` only when the subcategory token EXACTLY matches a
granular category under the row's module — by **slug** or **normalized
name**. No fuzzy fallback, no `[1]` fallback, no fabrication. If no exact
match → row stays `[]` for manual admin review (the P4 queue supports this).

## 4. Buckets (computed against post-repair taxonomy)
| Bucket | Count |
|--------|-------|
| Exact auto-matchable | **11** |
| └ module 14 (Beauty/Personal Care, enabled by repair) | 10 |
| └ module 13 (Retail/Shopping, already had cats) | 1 |
| Manual review still required | **373** |

Module breakdown of the 373 manual rows:
`4:77, 6:32, 13:28, 14:20, 5:44, 16:24, 10:23, 9:24, 11:28, 12:11, 8:29, 7:33`

### Why most remain manual
Subcategory tokens like `southern`, `seafood`, `soul_food`, `catering`,
`bakery`, `hair_salon`, `salon`, `barber`, `fashion` do not exactly match
any granular category slug/name in their module. They require an admin
decision (assign a sensible existing category, or leave `[]`).

## 5. Rows affected by beauty taxonomy repair
- **Module 14 pending rows: 30.** After the repair, module 14 has 20
  granular categories, enabling **10** of those 30 to auto-match by exact
  subcategory (e.g. `cosmetics`→824, `skincare`→823,
  `beauty_personal_care`→820). The remaining 20 module-14 rows still need
  manual review (subcats `hair_salon`, `salon`, `barber` have no exact
  slug match).

## 6. Rows affected by modules 14 / 15 being empty
- **Module 14**: was empty, now filled (20 cats) by the repair → backfill
  now possible for its pending rows (10 auto + 20 manual).
- **Module 15** ("beauty/hair"): still empty. No staged row maps to module
  15 (CSV has no "beauty/hair" business category), so backfill is
  unaffected. Resolving module 15 is a separate PM decision.

## 7. Rows that must remain category_ids = []
- Any pending row whose subcategory token has no exact category under its
  module (the 373 manual rows) must stay `[]` until an admin assigns a
  real category. `[]` is the honest, accepted pending state (per P3 rules).

## 8. Recommended next phase (category_ids backfill)
1. Build a guarded backfill command (e.g. `urban-goodz:category-backfill`)
   with `--dry-run` default and `--apply`, reusing the exact-match rule
   above. It would update ONLY `category_ids` on the 11 auto-matchable
   staged rows and report the 373 manual ones.
2. Run dry-run → confirm exactly 11 updates, 0 mismatches.
3. Apply only if exact and safe; never fabricate; never touch stores.
4. Manual queue: admins finish the 373 via the P4 review UI.
5. Re-run backfill readiness after module 15 / module 14 activation
   decisions to capture any newly matchable rows.

## 9. PM recommendation
- Do NOT auto-backfill in this phase.
- Approve a dedicated, guarded backfill command for the 11 exact matches
  only (separate phase, dry-run first).
- Keep the 373 manual rows `[]` until admin review.
- Resolve module 15 separately; activate module 14 only with PM approval.
