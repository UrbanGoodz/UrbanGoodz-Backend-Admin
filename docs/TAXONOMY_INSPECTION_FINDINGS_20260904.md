# Full Platform Taxonomy — Inspection Findings (Phase 1 of 2)
**Prepared:** 2026-09-04
**Status:** INSPECTION ONLY. Per D'Andre's mandate (see `HANDOFF_110_131_IMPORT_AND_TAXONOMY_20260904.md` §8), this is the required inspect-first phase. **No implementation, no plan for approval yet** — this is raw findings to inform that plan. Do not merge/change/deploy anything based on this doc alone.

---

## 1. Surfaces inspected so far (3 of 6)

### 1a. Backend DB schema — `modules` and `categories` tables

Real production `modules` table (17 active rows + 1 disabled demo):

| id | module_name | module_type | status | categories |
|---|---|---|---|---|
| 1 | Demo Module | grocery | disabled | 6 |
| 2 | Car Rental | rental | active | (rental, no category count relevant) |
| 3 | Pharmacy | pharmacy | active | 10 |
| 4 | Restaurants/Brick and Mortar | food | active | 6 |
| 5 | Grocery/Marketz | grocery | active | 20 |
| 6 | Home-Based Businesses | ecommerce | active | 20 |
| 7 | THC/CBD | ecommerce | active | 20 |
| 8 | Liquor/Beverages | ecommerce | active | 20 |
| 9 | Courier/Parcel Delivery | parcel | active | **0** |
| 10 | Pharmacy/Health | pharmacy | active | **0** |
| 11 | Local Events/Creators | ecommerce | active | 20 |
| 12 | Food Trucks | food | active | 21 |
| 13 | Retail/Shopping | ecommerce | active | 20 |
| 14 | Beauty/Personal Care | ecommerce | **disabled** | — |
| 15 | beauty/hair | ecommerce | **disabled** | — |
| 16 | Beauty Supply/Hair Providers | ecommerce | active | 40 |
| 17 | RideShare | ride-share | active | **0** |

**Findings:**
- **Duplicate/dead modules**: `Beauty/Personal Care` (14) and `beauty/hair` (15) are both disabled — legacy attempts at a Beauty module that were abandoned in favor of `Beauty Supply/Hair Providers` (16). Real cleanup candidate (delete or formally document as superseded, don't leave as ambiguous dead rows).
- **Zero-category modules**: Courier/Parcel (9), Pharmacy/Health (10), and RideShare (17) have no categories at all. Pharmacy/Health (10) is suspicious — there's ALSO a separate `Pharmacy` module (3) with 10 categories. Two Pharmacy modules, only one populated — same duplication pattern as Beauty.
- **`Restaurants/Brick and Mortar` (4) and `Food Trucks` (12) are two separate modules**, both `module_type: food` — but the existing `URBAN_GOODZ_BUSINESS_TYPES_AND_CAPABILITIES.md` registry treats "restaurant" as a single business type (#1). The registry doc and the real DB don't agree 1:1.
- **`RideShare` (17) is a live, active production module with zero categories, and it is NOT listed anywhere in the 18-business-type registry doc at all.** That doc is out of date relative to production.
- Confirmed earlier (previous session): category IDs are NOT reliably foreign-key-enforced — orphaned category_id references on `items` rows display fine (see `STORE_ITEM_BULK_IMPORT_110_131_HANDOFF_20260903.md` §4 for the 300/317 example). Any taxonomy migration needs to account for this — a clean cutover can't assume referential integrity currently exists.

### 1b. Customer app (`UrbanGoodz2026-Revised`)

`lib/features/urban_goodz/helper/module_priority.dart` — the code that orders modules on the home screen — **does its own free-text keyword matching** against `module.moduleName / moduleType / slug` to invent groupings that don't exist as real backend entities:

```dart
if (has(['retail', 'shopping'])) return 10;
if (has(['boutique', 'fashion', 'apparel', 'clothing', 'thrift', 'vintage'])) return 20;
if (has(['beauty', 'personal care', 'personalcare', 'salon', 'cosmetic'])) return 30;
if (has(['grocery', 'groceries', 'market', 'supermarket', 'produce'])) return 40;
if (has(['home based', 'home-based', 'homebased', 'independent seller'])) return 50;
if (has(['pharmacy', 'health', 'wellness', 'medical'])) return 60;
```

**Finding:** "Fashion" (weight 20) is treated as a distinct customer-facing concept HERE, purely via keyword-matching against module name/slug text — there is no backend module, business type, or category tree called "Fashion" anywhere. Same for "Beauty & Personal Care" as a unified concept — the backend has it split/broken across 3 modules (2 dead, 1 alive) as shown in §1a. **This file is the clearest single piece of evidence that a real taxonomy gap exists**: the client is compensating for the backend's lack of one, via fragile string matching that would silently misclassify any future module whose name doesn't contain the expected substring.

The file's own comment acknowledges the fragility: *"Matching looks at name, type and slug together because the production slugs are irregular: `retailshopping`, `grocerymarkets`, `pharmacyhealth`, `home-based-businesses`, `restaurantsbrick-and-mortar`."*

### 1c. Vendor app (`UrbanGoodz_Vendor_App/vendor_app/`)

`lib/controllers/vendor_auth_controller.dart` — `businessType` is populated directly from the store's raw module data:
```dart
businessType.value = module is Map
    ? module['module_name']?.toString() ?? module['module_type']?.toString()
    : ...
```
Same pattern as the customer app: no structured taxonomy field, just whatever free-text `module_name`/`module_type` the backend happens to return for that store's assigned module.

## 1d. `urban_goodz_business_types` — the table `URBAN_GOODZ_BUSINESS_TYPES_AND_CAPABILITIES.md` describes is DEAD, UNBUILT scaffolding

This is the single most important finding so far and changes the shape of any real plan.

- `UrbanGoodzBusinessTypeController` has a real CRUD UI (`create`, `edit`, `index`, `mapping` — all 4 blade views exist and render) for `urban_goodz_business_types` + a `mapping()` action that assigns `urban_goodz_capabilities` to a business type via a pivot (`urban_goodz_business_type_default_capabilities`).
- The `urban_goodz_business_types` table schema (`id, slug, name, description, icon, is_active, sort_order, created_at, updated_at`) has **no `module_id` or any other link to the real `modules` table at all.**
- Searched every controller and model for `business_type_id` usage: it appears **only** in the business-type model files themselves (`UrbanGoodzBusinessType.php`, `UrbanGoodzCapability.php`, `UrbanGoodzBusinessTypeDefaultCapability.php`) — **zero controllers for vendors, stores, or business clients ever read or write it.** Nothing in the live app assigns a business type to a real vendor/store.
- Confirmed directly against production: `DB::table('urban_goodz_business_types')->count()` → **0**. `DB::table('urban_goodz_business_type_default_capabilities')->count()` → **0**. The 18-row table the registry doc describes was never seeded into production at all.

**What this means:** `URBAN_GOODZ_BUSINESS_TYPES_AND_CAPABILITIES.md` is not documentation of a working system — it's a design spec for a system that was built (migrations, models, controller, admin UI) but never seeded or connected to anything real. There are, right now, **two entirely separate, unconnected taxonomy concepts in this codebase**: the real one (`modules`/`categories`, messy but actually driving the live storefront — see §1a-c) and the designed-but-dead one (`urban_goodz_business_types`/`urban_goodz_capabilities`, clean schema, zero real usage). Any taxonomy plan needs to explicitly decide: resurrect and wire up the dead-but-clean system, extend the messy-but-live system, or replace both with something new. That's a real architectural decision for the plan phase, not something to default into.

## 1e. Admin Panel category management UI — real URL and structure confirmed

The earlier redirect (`/admin/category/add-new`) was simply a wrong URL guess. The real route, per `routes/admin/routes.php` + `app/Enums/ViewPaths/Admin/Category.php`:
- **Add/list categories: `admin/category/add`** (not `add-new`).
- Full CRUD exists: add, edit `/update/{id}`, delete, status toggle, featured toggle, priority reorder.
- **A category-level bulk-import/bulk-export ALSO exists** (`admin/category/bulk-import`, `admin/category/bulk-export`) — separate from the item bulk-import used for the 110-131 store work. Worth knowing: if the taxonomy plan needs to seed a large new category tree (e.g., real subcategories for the 18 primary categories), this bulk path is the mechanism, same as items.
- The `category.add`/`category.edit` etc. routes carry `middleware('module:category')` — **the same session-based module-context pattern documented for item bulk-import in `STORE_ITEM_BULK_IMPORT_110_131_HANDOFF_20260903.md` §5 applies here too.** Any admin-UI category work (existing or new) is scoped by whatever module the session is currently on — this is a pervasive pattern across admin panel features, not unique to items.

## 1f. API layer — confirmed the module-scoping mechanism, and that it's NOT the same mechanism as the admin panel

`app/Http/Controllers/Api/V1/CategoryController.php` scopes category/item queries by `config('module.current_module_data')['id']` — same underlying config key the admin panel's session middleware sets, but the public API path is stateless (each customer-app request carries its own zone/module context via request headers, not a server session) while the admin panel is session-based. **Both ultimately read the same `module.current_module_*` config values, just populated differently per request type.** This is architecturally consistent (good), but means any taxonomy fix touching module-scoping needs to be tested against both request patterns, not just one.

## 2. All 6 surfaces now inspected — summary

| # | Surface | Status |
|---|---|---|
| 1 | Backend DB (`modules`, `categories`) | Done — §1a |
| 2 | Customer app | Done — §1b |
| 3 | Vendor app | Done — §1c |
| 4 | Business Portal / onboarding | Done via §1d (confirmed dead `business_types` system) |
| 5 | Admin Panel category management | Done — §1e |
| 6 | API layer | Done — §1f |

**Inspection phase is complete.** Per the mandate (`HANDOFF_110_131_IMPORT_AND_TAXONOMY_20260904.md` §8), the next step is producing an actual implementation plan from these findings and getting D'Andre's approval before any implementation — not yet done, and should not be started without that approval per the explicit gate.

## 3. Preliminary pattern (not yet a plan — needs the remaining 3 surfaces before one is credible)

Every surface answers "what type of business is this?" by reading raw, free-text `module_name`/`module_type` strings and interpreting them independently and inconsistently:
- Backend: literal DB rows, some duplicated/dead, some empty of categories, one (RideShare) undocumented.
- Customer app: keyword-matches those same strings into its own invented groupings (including "Fashion," which doesn't exist server-side at all).
- Vendor app: passes the raw string through with no interpretation.

This matches D'Andre's stated problem exactly: there is no single authoritative taxonomy — there's one messy backend table and N different client-side interpretations of it, and at least one of those interpretations (Fashion) has no server-side backing whatsoever.

**Do not act on this section as a plan.** The remaining 3 surfaces (business portal, admin category management, API layer) need inspection before a credible implementation plan can be written and brought to D'Andre for approval, per the mandate.
