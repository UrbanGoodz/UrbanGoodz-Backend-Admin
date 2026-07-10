# P2D INVALID URL BACKLOG RECOMMENDATION
## Urban Goodz — Phase Data-Import P2D (manual-review triage)

**Total invalid source_url rows: 26**

Source: `excluded_source_url_p2b.csv` (P2B dry-run). No URLs were fabricated.

---

## Why they were excluded
All 26 rows carry **Google-search placeholder URLs with unencoded spaces**,
for example:
```
https://www.google.com/search?q=Island%20Courier%20Services+Texas City+TX
```
The space in `Texas City+TX` breaks PHP `FILTER_VALIDATE_URL`, so the
URL is treated as invalid and the row is excluded. These are **not real
business URLs** — they are search-query references left over from sourcing.

## Confirmation — URLs were not fabricated
No replacement or synthetic URLs were generated. The importer and this triage
deliberately leave them excluded rather than invent a source.

## Recommended manual-review workflow
1. Keep all 26 rows **excluded** from the first real staging import.
2. Route them to a **manual-review backlog** keyed by business name + market.
3. For each, source a **real, verifiable URL**:
   - Official website
   - Google Business Profile
   - Verified social page (Instagram/Facebook/TikTok)
4. Once a real URL exists, the row can be re-added to a future import batch
   (after re-validation).

## Should these rows remain excluded from the first real staging import?
**Yes.** They must stay excluded until a real website/social/profile URL is
obtained. They are not staging-ready.

## Do they need real URLs before future import?
**Yes.** A valid `source_url` is the provenance requirement for a
`sourced_business` record. Without it, the row cannot enter as a valid
candidate.

Markets affected: Wharton/Brazoria-TX (5), Los Angeles-CA (7),
Las Vegas-NV (6), New York-NY (8).

See also: `P2D_INVALID_URL_BACKLOG.csv`, `INVALID_SOURCE_URL_BACKLOG.md`.
