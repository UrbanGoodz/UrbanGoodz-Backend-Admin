# P2D DUPLICATE TRIAGE RECOMMENDATION
## Urban Goodz — Phase Data-Import P2D (manual-review triage)

**Total duplicates: 18**
- **CSV-internal duplicates: 14**
- **Live-store `db_store_*` matches: 4**

Source: `duplicates_p2b.csv` (P2B dry-run). No live import has been run.
No live stores were merged, overwritten, or updated.

---

## CSV-internal duplicates (14)
These rows duplicate another row **within the import set** (matched on
name|city|state, website, or phone). They are already auto-excluded by the
importer and should **remain excluded** from the first real staging import.

Recommended action:
- **Exclude from the first real staging import** (do not manually approve).
- No PM review required — the collision is internal to the cleaned set.
- If a specific business is genuinely distinct (e.g. different legal entity),
  it can be re-sourced later with a disambiguating key.

Rows: Erk and Jerk Caribbean Cuisine, Amazing Street Beans HTX,
Sauce Another HTX, Fresh Houwse Grocery, Motherland African Food Market,
Da Vegan Guru, Doss Couture Designs, The Black Market HTX,
Earnestine & Hazel's, Texas Original - Dallas, Goodblend Texas - Dallas,
CBD Plus USA - Dallas, Texas Original - Austin, Your CBD Store - Charlotte.

## Live-store `db_store_*` matches (4)
These rows match an **existing live store** in the `stores` table:

| Business | City/State | Match |
|---|---|---|
| The Breakfast Klub | Houston, TX | `db_store_duplicate_name_city_or_address` |
| ChopnBlok | Houston, TX | `db_store_duplicate_name_city_or_address` |
| Sweet Georgia Brown | Dallas, TX | `db_store_duplicate_name_city_or_address` |
| Distant Relatives | Austin, TX | `db_store_duplicate_name_city_or_address` |

Recommended action:
- **Exclude from the first real staging import.**
- **Require separate PM review** before any decision.
- **Do NOT merge, overwrite, or update the live stores** represented by these
  matches. The staging import only writes to `urban_goodz_sourced_businesses`
  (a review table) and must never modify `stores`.
- Decision options per match: (a) keep excluded, or (b) import as a distinct
  `sourced_business` review record clearly separated from the live store.

## PM instruction
**Do not merge, overwrite, or update existing live stores** without a separate,
explicit review. Candidate stores/items/vendors are NEVER created by this import.

## Should any duplicate rows remain excluded from the first real staging import?
**Yes.** All 18 duplicate candidates should remain excluded from the first real
staging import. The 4 live-store matches additionally require manual PM review
before any future reconsideration.

See also: `P2D_DUPLICATE_TRIAGE.csv`, `DUPLICATE_REVIEW_REQUIRED.md`.
