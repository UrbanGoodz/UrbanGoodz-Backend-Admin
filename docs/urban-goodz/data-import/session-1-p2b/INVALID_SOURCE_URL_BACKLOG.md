# INVALID SOURCE URL — BACKLOG
## Urban Goodz — Phase Data-Import P2B

**26 rows** were excluded because their `source_url` failed validation.

### Root cause
These rows contain **Google-search placeholder URLs with unencoded spaces**,
for example:
```
https://www.google.com/search?q=Island%20Courier%20Services+Texas City+TX
```
The space in `Texas City+TX` breaks PHP `FILTER_VALIDATE_URL`, so the
URL is treated as invalid. These are **not real business URLs** — they are
search-query references left over from sourcing.

### Affected rows (by market)
- Wharton / Brazoria area, TX: Island Courier Services, Eaglin Motor Lines,
  Texas City Soul Food, Bay City BBQ, Sha BeBe Cajun Cafe
- Los Angeles, CA: The Blk Lifestyle, ConditionHER Hair, Contented Nail Parlor,
  Britt Brow, Crystal Eyes Healing, Mae's Courier LA, Soul 2 Soul Global Events
- Las Vegas, NV: 1QTEE Boutique, Slauson Grill, Hibachi Vegas Food Truck,
  Afiya Express Courier, Sweets by Sherell, Vegas Health First Pharmacy
- New York, NY: Harlem Health Pharmacy, Big Apple Courier, Harlem Events Collective,
  Piece of Cake Bakery, Harlem Nail Studio, Brooklyn Barber Co.,
  The Brownstone Event Space, Uptown Mobile Deli

### Handling rules
- **Do NOT fabricate or generate replacement source URLs.**
- Keep these rows **excluded** from the import.
- Route them to a **manual-review backlog** for a real source URL to be
  sourced later (e.g. official site, Google Business Profile, social page).

### Source of truth
- Full list: `excluded_source_url_p2b.csv`
- Dry-run reason: `missing_or_invalid_source_url` (26)
