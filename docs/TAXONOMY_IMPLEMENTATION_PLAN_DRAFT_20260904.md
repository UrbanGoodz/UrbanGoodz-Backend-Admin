# Full Platform Taxonomy — Implementation Plan (DRAFT, PENDING D'ANDRE'S APPROVAL)
**Prepared:** 2026-09-04
**Status: DRAFT ONLY. Nothing in this document has been implemented, and nothing should be implemented from it without D'Andre's explicit approval, per the mandate's inspect → plan → approve → implement gate.**
**Based on:** `TAXONOMY_INSPECTION_FINDINGS_20260904.md` (all 6 surfaces inspected, committed).

---

## 1. The core decision this plan needs approved

Two real, disconnected taxonomy systems exist today (see findings §1d):

| | `modules` / `categories` | `urban_goodz_business_types` / `urban_goodz_capabilities` |
|---|---|---|
| Status | Live, actually drives the storefront, messy | Built, zero rows, zero real usage — dead |
| Schema quality | Ad-hoc, some duplicate/dead rows, no clean hierarchy | Clean, has a capability-matrix concept already designed |
| Risk to touch | High — every surface reads it today | Low — nothing depends on it yet |

**Three options:**

**Option A — Extend the live system.** Add the 18 primary categories as real, clean top-level entries (fixing duplicates like the two dead Beauty modules and two Pharmacy modules along the way), keep `modules`/`categories` as the single source of truth, retire the dead `business_types` tables entirely. Lowest risk to the live storefront since it's incremental cleanup of what already works; loses the capability-matrix concept unless it's rebuilt on top of `modules`.

**Option B — Resurrect and wire up `business_types`.** Seed the 18 primary categories into the clean, unused table, add a real `module_id` (or many-to-many) link from `business_types` to `modules`, and migrate every surface (customer app's `module_priority.dart`, vendor app's `businessType` field, admin panel) to read the structured table instead of free-text matching. Higher effort (touches every surface's read path) but produces the actually-clean single-source-of-truth D'Andre asked for, including the capability matrix that's already designed and ready to use.

**Option C — Replace both with something new.** Not recommended — Option B's schema is already well-designed and unused, building a third system would be pure waste.

**Recommendation: Option B**, on the reasoning that D'Andre's mandate specifically asks for "one authoritative taxonomy... consumed by ALL surfaces" — Option A achieves that only by accepting the live system's existing mess as permanent, while Option B is a clean, already-half-built path to the actual ask, and the capability matrix (which surface gets which admin sections, which order flows apply) has zero cost to preserve since it already exists and works, it's just never been connected to anything.

**This is D'Andre's call, not an engineering default — flag both options clearly and let him choose before writing a single line of implementation.**

## 2. Mapping D'Andre's 18 primary categories to current reality (if Option B is chosen)

| # | D'Andre's primary category | Existing `modules` row(s) it should absorb | Existing `business_types` slug it should use/create | Gap severity |
|---|---|---|---|---|
| 1 | Food & Restaurants | Restaurants/Brick and Mortar (4), Food Trucks (12) | `restaurant` (exists in doc, unseeded) | Low — just needs seeding + a decision on whether Food Trucks stays a subtype or its own row |
| 2 | Grocery & Everyday Essentials | Grocery/Marketz (5) | `grocery` | Low |
| 3 | Retail & Shopping | Retail/Shopping (13) | `retail` | Low |
| 4 | Fashion | **None** — currently a client-side keyword-match over Retail/Shopping (13) + Home-Based Businesses (6) | **No slug exists** | **High — this is the headline gap.** Needs a real module or a real subcategory split, not just a business-type row, since items need somewhere to actually live. |
| 5 | Beauty & Personal Care | Beauty Supply/Hair Providers (16) active; Beauty/Personal Care (14) + beauty/hair (15) dead | `beauty-supply` | Medium — needs the two dead modules formally retired, not just ignored |
| 6 | Home Services | **None** | **No slug exists** | **High — genuinely nothing today.** No module, no business type, no categories. |
| 7 | Automotive & Roadside (incl. Stranded) | Car Rental (2) covers rental only; Stranded has its own `UrbanGoodzStranded*` tables per the inspection findings, separate system entirely | Not in the 18-type doc at all | Medium — Stranded already has real backend tables (confirmed to exist), just needs to be positioned under this umbrella, not built fresh |
| 8 | Healthcare & Pharmacy | Pharmacy (3) + Pharmacy/Health (10, empty) — duplicate | `pharmacy` | Medium — same duplicate-module cleanup as Beauty |
| 9 | Delivery & Courier | Courier/Parcel Delivery (9, empty categories) | `courier` | Medium — module exists but has zero categories to seed from |
| 10 | Freight & Logistics | Not a `modules` row — `UrbanGoodzLoadBoard*` tables per findings | `logistics` | Low — real backend tables already exist per prior session's inspection, just not modeled as a "module" the same way |
| 11 | Events & Experiences | Local Events/Creators (11) | `events` | Low |
| 12 | Creators & Entertainment | Overlaps Local Events/Creators (11) — same module currently serves both | `creator-commerce` | Medium — needs the split D'Andre's taxonomy implies (Events vs. Creators as distinct top-level categories) that the current single module doesn't support |
| 13 | Rentals | Car Rental (2); `UrbanGoodzRental*` tables per findings cover equipment too | `car-rental`, `equipment-rental` | Low — real tables exist |
| 14 | Professional Services | **None** as a module — `book-anything` capability exists in the dead business_types design | `professional-services` | High — no live categories/items path exists for this today |
| 15 | Personal Services | **None**, and unclear how this differs from #14 in the current design | Not in the 18-type doc | **High — needs D'Andre's own clarification on the Personal vs. Professional Services split before this can even be scoped**, this isn't an engineering gap, it's a definitional one |
| 16 | Pets & Animal Services | **None at all** — zero mentions anywhere in modules, categories, or business_types | Not in the 18-type doc | **High — total gap, nothing exists** |
| — | Stranded (cross-cutting) | Real `UrbanGoodzStranded*` tables exist per findings | n/a — capability, not a category | Low, just needs correct positioning |
| — | Order Anywhere (cross-cutting) | Confirmed real and working (`UrbanGoodzOrderAnywhere*`) | Already correctly modeled as a capability, not a business type, per the existing registry doc's own "Order Anywhere Ownership" section | None — this one's actually done right already |

## 3. What this plan does NOT cover yet

- No concrete migration scripts, no specific new module/category rows, no code changes to `module_priority.dart` or the vendor app — none of that should be written until D'Andre picks A vs. B in §1.
- No answer on the Personal Services vs. Professional Services definitional question (row 15 above) — that needs D'Andre directly, it's not resolvable by more code inspection.
- No estimate of effort/timeline — reasonable to produce once the option is chosen, since Option A and B have very different scopes.

## 4. Recommended next step

Present §1's two real options (plus the recommendation and why) to D'Andre directly, get a decision, then come back and turn §2's gap table into an actual scoped task list for the chosen option. Do not proceed further on this plan without that decision.
