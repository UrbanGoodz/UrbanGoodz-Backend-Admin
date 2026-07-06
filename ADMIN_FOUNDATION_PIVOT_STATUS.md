# Admin Foundation Pivot — Status Document

## Pivot Direction
Stopping customer-app / feature-by-feature work. Pivoting to **admin/backend-first, white-label/config-driven foundation** using 6amMart admin/vendor role-permission structure as the base.

## Paused WIP Before Admin Foundation Pivot

### Backend/Admin Repo — Uncommitted Changes

**Modified (tracked) — 13 files:**
| File | Changes |
|------|---------|
| `app/CentralLogics/Helpers.php` | +20 lines — helper utilities |
| `app/Http/Controllers/Admin/UrbanGoodzAdminController.php` | +29 lines — file library method |
| `app/Http/Controllers/Admin/UrbanGoodzFashionMeasurementController.php` | +76/-4 lines — photo gallery + tailor assignment |
| `app/Http/Controllers/Api/V1/OrderAnywhereTesterController.php` | +1 line — minor |
| `app/Http/Controllers/Api/V1/UrbanGoodzDiscoveryController.php` | +272/-67 lines — significant discovery work |
| `app/Models/MeasurementRequest.php` | +21 lines — photo file relationships |
| `app/Models/OrderAnywhereRequest.php` | +22 lines — itemized pricing fields |
| `app/Models/Store.php` | +156/-1 lines — business_type_slug, capabilities, partner badge |
| `app/Traits/PlaceNewOrder.php` | +95 lines — order placement trait |
| `database/seeders/DatabaseSeeder.php` | +2 lines — registered new seeders |
| `resources/views/admin-views/urban-goodz/fashion-measurements/view.blade.php` | +87/-4 lines — photo gallery + tailor field |
| `routes/admin.php` | +8 lines — file library + AI concierge routes |
| `routes/api/v1/urban_goodz.php` | +13 lines — file upload + AI concierge API routes |

**Untracked (new) — 26+ files:**
- 7 new migrations (business_types, capabilities, business_capabilities, business_type_slug, itemized_pricing, ai_intents, ai_conversations)
- 7 new models (UrbanGoodzBusinessType, Capability, BusinessCapability, BusinessTypeDefaultCapability, AIIntent, AIConversation)
- 2 new services (UrbanGoodzFileStorageService, UrbanGoodzAIConciergeService)
- 3 new API controllers (FashionFitFileController, UrbanGoodzFileUploadController, UrbanGoodzAIConciergeController)
- 1 new admin controller (UrbanGoodzAIConciergeController)
- 3 seeders (BusinessType, AIIntent, Ingestion)
- 4 admin views (file library index, AI intents, conversations list, conversation detail)
- 1 config file (urban_goodz_admin_sections.php)
- 1 existing audit doc (ADMIN_FOUNDATION_AUDIT.md)

### Customer App Repo — Uncommitted Changes
- `OPEN_CODE_STATUS.md` — status doc update
- `lib/features/urban_goodz/fashion_measurements/services/fashion_measurement_api_service.dart` — API URL fix (405 root cause fix)

### Vendor App Repo — Clean (no uncommitted changes)

### Driver App Repo — Clean (no uncommitted changes)

### What Was NOT Done
- Migrations were **not run** (MySQL not available locally)
- Flutter analyze was **not completed** (timed out)
- APK was **not rebuilt**
- Work was **not committed** anywhere

---

## Pivot Rules
1. Every customer-facing feature must have a backend endpoint, admin panel, and permission control before customer app work.
2. Do not build separate apps per business type.
3. Do not hardcode features in Flutter; backend controls visibility via config.
4. Extend existing 6amMart admin/vendor permission structure; do not build new admin permissions from scratch.
5. Order Anywhere is owned by Master Admin / Urban Goodz Operations, not by individual vendors.
6. All files from paused WIP remain in place for later use.
