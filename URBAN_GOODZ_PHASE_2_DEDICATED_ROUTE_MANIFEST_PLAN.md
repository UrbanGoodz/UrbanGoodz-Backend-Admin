# Urban Goodz — Phase 2: Dedicated Route Manifest System

**Status:** Planning/Audit Only  
**Date:** 2026-07-08  
**Demo Build:** Locked (Session 1 + 2 complete)  
**Next Phase:** Dedicated Route Manifest Builder  

---

## 1. Current Reusable Pieces

The following code is already built and can be reused or extended for the Dedicated Route Manifest system:

| Component | File(s) | Status | Notes |
|-----------|---------|--------|-------|
| Package Scanner | `BusinessPortalController::scanStore()`, `resources/views/business/routes/packages/scan.blade.php` | ✅ Working | Scans barcode, creates packages with `manifest_session_id`. Scoped to authenticated business/employee. Returns JSON. |
| Package Pool | `BusinessPortalController::packagePool()`, `resources/views/business/routes/packages/pool.blade.php` | ✅ Working | Lists unassigned packages (`dedicated_route_id IS NULL`). Supports assignment to route. |
| Business Employee Scoping | `BusinessAuthController`, `BusinessMiddleware`, `getClientId()` | ✅ Working | Auth guard `business` scopes all data to `business_client_id`. No cross-business visibility. |
| Route Package Model | `app/Models/UrbanGoodzRoutePackage.php` | ✅ Working | Central model. Has `manifest_session_id`, `scanned_by`, `scanned_at`, `geocode_status`, `geocode_confidence`, `dropoff_lat/lng`, payout fields. 33 fillable fields, 10 relations. |
| Dedicated Route Model | `app/Models/UrbanGoodzDedicatedRoute.php` | ✅ Working | Has `pickup_lat/lng`, `end_lat/lng`, `payout_model`, `estimated_miles`, `estimated_duration`, `assigned_driver_id`, `status` workflow. |
| End-Location Optimization | `BusinessPortalController::routeOptimize()`, `UrbanGoodzRouteOptimizationStop` | ✅ Working | Haversine distance sorting. Falls back to priority sort if no coordinates. Creates ordered optimization stops. |
| Package Assignment to Route | `BusinessPortalController::assignPackageToRoute()` | ✅ Working | Assigns unassigned package to route, increments `total_packages`, sets `stop_order`. |
| Route Batches Model | `app/Models/UrbanGoodzRouteBatch.php` | ✅ Working | Groups packages into batches under a route. Has `assigned_driver_id`, `status` workflow. |
| Package Scan Events Model | `app/Models/UrbanGoodzPackageScan.php` | ✅ Working | Records scan events per package. SCAN_TYPES includes `business_package_scan`, `pickup`, `dropoff`, `exception`, `return_scan`, etc. |
| Route Assignment Model | `app/Models/UrbanGoodzRouteAssignment.php` | ✅ Working | Tracks driver assignment to routes. STATUSES: assigned, accepted, en_route, started, completed, canceled. |
| Medical Custody Log | `app/Models/UrbanGoodzMedicalCustodyLog.php` | ✅ Working | Chain-of-custody tracking per package. Events: pickup, handoff, dropoff, temp_check, seal_check, exception. |
| Driver Earning Model | `app/Models/UrbanGoodzDriverEarning.php` | ✅ Working | EARNING_TYPES: per_package, pickup_bonus, completion_bonus, priority_bonus, partial_pay, return_pay. |
| Driver Payout Request Model | `app/Models/UrbanGoodzDriverPayoutRequest.php` | ✅ Working | PAYOUT_TYPES: instant, weekly, held. STATUSES: pending, approved, processing, paid, rejected, held. |
| Client Invoice Model | `app/Models/UrbanGoodzClientInvoice.php` | ✅ Working | Supports route/batch/summary invoice types. Connects to routes. |
| Payment Ledger System | `app/Models/UrbanGoodzPaymentLedger`, `UrbanGoodzPaymentSplit` | ✅ Working | Generic payment tracking with splits. Can be extended for dedicated route billing. |
| Admin Dedicated Routes CRUD | `resources/views/admin-views/urban-goodz/dedicated-routes/*` (7 views) | ✅ Built | Index, create, show, edit, packages, package-show, scans, report views exist. |
| Admin Driver Payouts Views | `resources/views/admin-views/urban-goodz/driver-payouts/*` (3 views) | ✅ Built | Index, show, earnings views exist. |
| Admin Business Clients CRUD | `UrbanGoodzBusinessClientController` + 15 views | ✅ Built | Full CRUD for clients, users, locations, documents. Driver assignment in job workflow. |
| Driver API Routes | `routes/api/v1/urban_goodz.php` lines 98-109 | ✅ Built | Assigned routes, scan pickup/dropoff/exception, earnings, payout-request, payout-history. |
| Business Client Job Model | `app/Models/UrbanGoodzBusinessClientJob.php` | ✅ Built | 27 fillable fields covering pickup, dropoff, medical courier, load details, financial, assignment, tracking. **Possibly mergeable with manifest workflow.** |

---

## 2. Missing Pieces

| Component | Priority | Description |
|-----------|----------|-------------|
| Manifest/Session Model | **Critical** | A dedicated `urban_goodz_manifests` table to group packages before route assignment. Currently `manifest_session_id` is a UUID string on packages. Need a proper model with pickup location, date, status, package count, imported_by. |
| Bulk Package Import | **Critical** | CSV import endpoint + UI for importing many packages at once. CSV parser exists in `routePackageBulkStore()` but needs to be adapted for manifest-first flow (no route ID required). |
| Address Validation | **High** | Validate addresses during scan/import. Need validation rules + UI for flagging invalid addresses. Currently no server-side address validation. |
| Geocoding Provider Integration | **High** | Convert addresses to lat/lng. Fields exist (`dropoff_lat/lng`, `geocode_status`, `geocode_confidence`) but no provider is integrated. Need Google Maps / Mapbox / OpenStreetMap integration. |
| Route Batch Generator | **High** | Algorithm to group packages into Route A/B/C batches based on grouping rules (packages per route, max radius, max miles, ZIP/city/proximity, delivery window, vehicle type). |
| Grouping Rules UI | **High** | Business-facing form to configure how packages should be grouped. |
| Driver Assignment Workflow | **High** | Admin UI to assign generated route batches to drivers. Exists for individual routes but needs batch-aware assignment. |
| Proof of Delivery | **High** | Photo capture + signature capture during driver dropoff. Fields exist on tables (`proof_photo`, `recipient_signature`, `delivery_result`, `delivered_to_name`) but no front-end capture workflow. |
| Failed Delivery / Return Workflow | **High** | Process for marking packages undeliverable, generating return route, scanning return. Fields exist (`return_required`, `returned_at`, `return_location`, `exception_reason`). |
| Package/Fixed Route Payout Rules | **Medium** | Calculate driver pay based on package count or fixed batch rate (not mileage). Fields exist (`driver_pay_per_package`, `route_offer_amount`, `payout_model`) but no calculation engine. |
| Address Autocomplete | **Medium** | During manual entry, autocomplete addresses. UX improvement for high-volume entry. |
| OCR Label Extraction | **Low** | Extract barcode/tracking/address from package label photo. Nice-to-have, fallback to manual entry. |

---

## 3. Proposed Database Design

### 3a. New Table: `urban_goodz_manifests`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | |
| business_client_id | bigint unsigned FK | Who owns this manifest |
| manifest_name | varchar(255) | Optional label |
| pickup_location_id | bigint unsigned FK nullable | Linked to business client location |
| pickup_location_text | text nullable | Free-text pickup (if no location linked) |
| pickup_lat | decimal(10,7) | nullable |
| pickup_lng | decimal(10,7) | nullable |
| scheduled_date | date | Pickup/delivery date |
| delivery_window_start | time nullable | Earliest delivery time |
| delivery_window_end | time nullable | Latest delivery time |
| total_packages | int default 0 | Count at time of creation |
| valid_address_count | int default 0 | After geocoding validation |
| invalid_address_count | int default 0 | After geocoding validation |
| geocode_status | varchar(50) default 'pending' | pending, processing, completed, partial, failed |
| status | varchar(50) default 'draft' | draft, importing, import_complete, validating, validated, grouping, grouped, approved, canceled |
| import_method | varchar(50) nullable | scan, csv_upload, manual, api |
| csv_filename | varchar(255) nullable | Original CSV file name |
| grouped_by | varchar(50) nullable | auto, manual |
| grouping_rules | json nullable | Snapshot of rules used for grouping |
| created_by | bigint unsigned FK nullable | business user who created |
| approved_by | bigint unsigned FK nullable | admin who approved |
| approved_at | timestamp nullable | |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | softDeletes |

**Indexes:**
- `ug_mnf_client_status` on (business_client_id, status)
- `ug_mnf_client_date` on (business_client_id, scheduled_date)

### 3b. New Table: `urban_goodz_manifest_grouping_rules`

Store the rules chosen for each manifest's route batch generation.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | |
| manifest_id | bigint unsigned FK | |
| packages_per_route_max | int default 50 | |
| max_route_radius_miles | decimal(10,2) nullable | |
| max_route_miles | decimal(10,2) nullable | |
| grouping_method | varchar(50) default 'zip' | zip, city, proximity, delivery_window, vehicle_type |
| vehicle_type_required | varchar(50) nullable | |
| delivery_window_grouping | boolean default false | |
| proximity_radius_miles | decimal(10,2) nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 3c. New Table: `urban_goodz_manifest_packages`

Junction/pivot between manifests and packages (if we want many-to-many).  
**Alternative:** Keep `manifest_session_id` on `urban_goodz_route_packages` and add `manifest_id` FK.  
**Recommendation:** Add `manifest_id` FK (nullable) directly to `urban_goodz_route_packages` table.

Column to add to `urban_goodz_route_packages`:
- `manifest_id` bigint unsigned FK -> urban_goodz_manifests (nullable)
- `address_validation_status` varchar(50) default 'pending' (pending, valid, invalid, geocoded, geocode_failed)
- `address_validation_message` text nullable

### 3d. New Table: `urban_goodz_delivery_proofs`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | |
| package_id | bigint unsigned FK | |
| proof_type | varchar(50) | photo, signature, note, gps_coordinate |
| file_path | text nullable | For photos/signatures |
| notes | text nullable | |
| latitude | decimal(10,7) nullable | |
| longitude | decimal(10,7) nullable | |
| captured_by | bigint unsigned FK nullable | delivery_man_id |
| captured_at | timestamp | |
| created_at | timestamp | |

### 3e. Fields to add to `urban_goodz_route_packages` (existing):

These fields already exist on the model — verify migration coverage:

| Field | Exists? | Migration |
|-------|---------|-----------|
| `delivery_result` | ✅ | `2026_07_08_100000` |
| `delivered_to_name` | ✅ | same |
| `delivered_location_type` | ✅ | same |
| `return_required` | ✅ | same |
| `returned_at` | ✅ | same |
| `return_location` | ✅ | same |
| `payout_status` | ✅ | same |
| `payout_eligible` | ✅ | same |
| `geocode_status` | ✅ | same |
| `geocode_confidence` | ✅ | same |
| `stop_order` | ✅ | same |
| `dropoff_city` | ✅ | same |
| `dropoff_state` | ✅ | same |
| `dropoff_zip` | ✅ | same |
| `manifest_session_id` | ✅ | `2026_07_08_110000` |
| `scanned_by` | ✅ | same |
| `scanned_at` | ✅ | same |
| `manifest_id` | ❌ **MISSING** | Needs new migration |

---

## 4. Proposed Admin Workflow (Pages/Controllers to Build)

### Phase 2A — Manifest Management
- `/admin/urban-goodz/manifests` — List all manifests (filterable by client, status, date)
- `/admin/urban-goodz/manifests/{id}` — Show manifest detail (package list, validation status, grouping options)
- `/admin/urban-goodz/manifests/{id}/validate` — Trigger geocoding validation
- `/admin/urban-goodz/manifests/{id}/group` — Generate route batches (with rule config)
- `/admin/urban-goodz/manifests/{id}/approve` — Approve generated routes

### Phase 2B — Route Batch Review
- Already has: `dedicated-routes/index`, `dedicated-routes/show`
- Need: Batch-level review UI showing packages grouped by batch

### Phase 2C — Driver Assignment
- Already has: Route assignment via `UrbanGoodzDedicatedRouteController`
- Need: Batch-level driver assignment UI

### Phase 2D — Payout Review
- Already has: `driver-payouts/index`, `driver-payouts/show`, `driver-payouts/earnings`
- Need: Calculation engine that sets payout per package/batch based on rules

---

## 5. Proposed Business Workflow (Pages to Build)

### Phase 2A — Manifest Intake
- `/business/manifests` — List manifests
- `/business/manifests/create` — Select pickup location + date
- `/business/manifests/{id}/import` — Scan/import packages into manifest (reuse existing scanner)
- `/business/manifests/{id}/review` — Review imported packages, see validation status
- `/business/manifests/{id}/group` — Choose grouping rules, request route generation

### Modify Existing Business Pages
- `/business/packages/scan` — Already exists. Add `manifest_id` to scan response. Optionally scope scan to active manifest.
- `/business/packages/pool` — Already exists. Filter by manifest. Show manifest grouping status.

---

## 6. Proposed Driver Workflow

| Step | Action | API Endpoint | Status |
|------|--------|-------------|--------|
| 1 | View assigned batches | `GET /api/v1/urban-goodz/driver/routes` | ✅ Exists |
| 2 | Accept batch | `POST .../routes/{id}/accept` | Needs endpoint |
| 3 | Scan pickup | `POST .../packages/{id}/scan-pickup` | ✅ Exists (`/api/v1/urban-goodz/driver/scan-pickup`) |
| 4 | View ordered stops | `GET .../routes/{id}/stops` | Needs endpoint |
| 5 | Mark delivered (with photo/sig) | `POST .../packages/{id}/deliver` | ✅ Partial (`/api/v1/urban-goodz/driver/scan-dropoff`) |
| 6 | Mark unable to deliver | `POST .../packages/{id}/exception` | ✅ Exists (`/api/v1/urban-goodz/driver/exception`) |
| 7 | Scan return | `POST .../packages/{id}/return` | Needs endpoint |
| 8 | View earnings | `GET /api/v1/urban-goodz/driver/earnings` | ✅ Exists |
| 9 | Request payout | `POST /api/v1/urban-goodz/driver/payout-request` | ✅ Exists |
| 10 | View payout history | `GET /api/v1/urban-goodz/driver/payout-history` | ✅ Exists |

---

## 7. Payout Model

### Dedicated Routes (New — Package/Fixed Batch)
- **Base pay:** Per-package rate or fixed route/batch offer amount
- **Not mileage-based** (unlike courier routes)
- **Undelivered packages:** Not paid as delivered
- **Return scan required:** For chain of custody on undelivered packages
- **Bonus types:** Pickup bonus, route completion bonus, priority package bonus
- **Partial pay:** Failed delivery partial pay rate, return-to-sender pay rate
- **Payout models available:** `per_package`, `route_offer`, `hybrid`
- **Frequency:** Instant (with fee) or weekly
- **Admin approval:** Required before payout is released

### Courier Routes (Existing — Do Not Change)
- **Base pay:** Mileage-based
- **Already working**

### Last-Mile Delivery
- **Base pay:** Mileage-based
- **Already working**

### Relevant Model Fields (Already Exist)
- `UrbanGoodzDedicatedRoute`: `payout_model`, `driver_pay_per_package`, `business_charge_per_package`, `route_offer_amount`, `pickup_bonus`, `route_completion_bonus`, `priority_package_bonus`, `failed_delivery_partial_pay`, `return_to_sender_pay`, `instant_payout_allowed`, `weekly_payout_allowed`
- `UrbanGoodzRoutePackage`: `payout_status`, `payout_eligible`
- `UrbanGoodzDriverEarning`: `earning_type` (per_package, pickup_bonus, completion_bonus, priority_bonus, partial_pay, return_pay), `amount`, `status`

---

## 8. Recommended Implementation Phases

### Phase 2A: Manifest Model + Intake Screen
**Effort:** Medium  
**Scope:**
1. Create `urban_goodz_manifests` table (migration)
2. Create `UrbanGoodzManifest` model
3. Add `manifest_id` FK to `urban_goodz_route_packages` (migration)
4. Create business portal manifest views (create, show, import)
5. Modify scan endpoint to optionally accept `manifest_id`
6. Create admin manifest management views
7. Create `UrbanGoodzManifestController` (admin) + manifest methods in `BusinessPortalController`

**Files to create:**
- `database/migrations/2026_07_XX_000001_create_urban_goodz_manifests_table.php`
- `database/migrations/2026_07_XX_000002_add_manifest_id_to_route_packages.php`
- `app/Models/UrbanGoodzManifest.php`
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzManifestController.php`
- `resources/views/business/manifests/` (3-4 views)
- `resources/views/admin-views/urban-goodz/manifests/` (3-4 views)

**Files to modify:**
- `routes/business.php` (add manifest routes)
- `routes/admin.php` (add manifest routes)
- `app/Http/Controllers/Admin/UrbanGoodz/BusinessPortalController.php` (add manifest methods + modify scanStore)
- `resources/views/business/layouts/app.blade.php` (add sidebar links)
- `resources/views/layouts/admin/partials/_sidebar.blade.php` (add admin sidebar links)

### Phase 2B: Bulk Import / OCR Placeholder + Manual Fallback
**Effort:** Medium  
**Scope:**
1. CSV import for manifests (reuse/adapt `routePackageBulkStore`)
2. Manual package entry form for manifests
3. OCR placeholder — file upload field for label image, stub processing

### Phase 2C: Address Validation / Geocoding
**Effort:** High  
**Scope:**
1. Integrate geocoding provider (Google Maps / Mapbox / OpenStreetMap Nominatim)
2. Batch geocode all packages in a manifest
3. UI for showing valid/invalid addresses
4. Manual address correction for invalid entries
5. Populate `dropoff_lat/lng`, `pickup_lat/lng`, `geocode_status`, `geocode_confidence`

### Phase 2D: Route Batch Generation
**Effort:** High  
**Scope:**
1. Build grouping algorithm (ZIP-based, proximity-based, count-based)
2. Create grouping rule configuration UI
3. Generate `UrbanGoodzDedicatedRoute` + `UrbanGoodzRouteBatch` records from manifest
4. Generate `UrbanGoodzRouteOptimizationStop` records with ordered stops
5. Show generated routes to business for review

### Phase 2E: Driver Assignment
**Effort:** Medium  
**Scope:**
1. Admin UI to assign generated route batches to drivers
2. Driver notification (if notification system in place)
3. Driver accept/decline flow

### Phase 2F: Proof / Return Workflow
**Effort:** Medium  
**Scope:**
1. Photo capture during dropoff (frontend)
2. Signature capture (frontend)
3. `UrbanGoodzDeliveryProof` model and storage
4. Unable to deliver flow — reason capture, return scan
5. Return-to-business workflow

### Phase 2G: Payout Rules / Admin Review
**Effort:** Medium  
**Scope:**
1. Build payout calculation engine (per-package, fixed offer, hybrid)
2. Auto-generate `UrbanGoodzDriverEarning` records on delivery
3. Admin payout approval/review UI
4. Payout request processing
5. Client invoice generation

---

## 9. Risk List

| Risk | Severity | Mitigation |
|------|----------|------------|
| **Migrations conflicting with deployed demo** | High | Name migrations with future dates. Test on staging before production. |
| **Route name conflicts** | Medium | Keep dedicated route routes under distinct prefixes (`/business/manifests`, `/business/dedicated-routes`). Never run `route:cache`. |
| **Cross-business data visibility** | High | All queries must scope by `business_client_id`. Reuse existing `getClientId()` pattern. |
| **Scanner security bypass** | Critical | Already locked down. Phase 2 must not introduce new public scan endpoints. |
| **Duplicate package detection** | Medium | Barcode is unique. `manifest_session_id` + barcode could allow cross-manifest duplicates. Add manifest-level uniqueness check. |
| **Payout accuracy** | High | Double-entry accounting pattern. Use `UrbanGoodzPaymentLedger` for immutable audit trail. |
| **Driver app dependency** | High | Driver workflow requires mobile app or mobile-web interface. Current driver API endpoints exist but may need companion app updates. |
| **Geocoding provider dependency** | Medium | Google Maps requires API key + billing. Plan for OpenStreetMap Nominatim as free fallback (rate-limited). |
| **Courier route confusion** | Medium | Both use `UrbanGoodzDedicatedRoute` model but different status flows. Clearly separate via `route_type` or payout model. |
| **Performance with large manifests** | Medium | Batch geocoding and route generation for 500+ packages needs queue job. Consider Laravel job/batch processing. |

---

## 10. Build Rules (Phase 2)

1. **Do not modify locked demo files** until explicitly approved.
2. **No migrations without written approval.** Migrations must be reversible.
3. **Never run `php artisan route:cache`.** Use `route:clear` only.
4. **Keep courier routes separate from dedicated routes.** They share the same model but have different workflows and payout models.
5. **All new tables must have `softDeletes`** for consistency with existing schema.
6. **All business-facing queries must scope by `business_client_id`.** Reuse `getClientId()` pattern.
7. **Do not introduce new public/unauthenticated scan endpoints.** Scanner must remain authenticated and business-scoped.
8. **Payout logic must use double-entry through `UrbanGoodzPaymentLedger`** for audit trail.
9. **Test on staging before production.** Verify no regressions in courier routes, scanner, or package pool.

---

## Appendices

### A. Models That Exist But Are Missing From Admin Sidebar

These have full routes, controllers, and views but are not linked in the admin navigation:

- `admin.urban-goodz.business-clients.*` — Full CRUD exists
- `admin.urban-goodz.dedicated-routes.*` — Full CRUD exists
- `admin.urban-goodz.driver-payouts.*` — Full CRUD exists
- `admin.urban-goodz.driver-earnings.*` — List exists

**Recommendation:** Add sidebar links for these before or during Phase 2A.

### B. Business Portal Routes That Already Serve Dedicated Route Needs

| Route | Method | Controller | Purpose |
|-------|--------|------------|---------|
| `GET /business/packages/scan` | `scanPackages()` | BusinessPortalController | Scanner page |
| `POST /business/packages/scan` | `scanStore()` | BusinessPortalController | Scan intake |
| `GET /business/packages/pool` | `packagePool()` | BusinessPortalController | Unassigned package list |
| `POST /business/packages/{id}/assign` | `assignPackageToRoute()` | BusinessPortalController | Assign to route |
| `GET /business/routes/{id}/packages` | `routePackages()` | BusinessPortalController | Route package list |
| `POST /business/routes/{id}/packages` | `routePackageStore()` | BusinessPortalController | Add package to route |
| `GET /business/routes/{id}/packages/upload` | `routePackageUpload()` | BusinessPortalController | CSV upload form |
| `POST /business/routes/{id}/packages/upload` | `routePackageBulkStore()` | BusinessPortalController | CSV import |
| `GET /business/routes/{id}/optimize` | `routeOptimize()` | BusinessPortalController | Optimize stops |

### C. Key Controller Files for Phase 2

| File | Lines | Role |
|------|-------|------|
| `BusinessPortalController.php` | 634 | Business portal logic — will need manifest methods added |
| `UrbanGoodzBusinessClientController.php` | 537 | Admin client management — driver assignment exists |
| `UrbanGoodzDedicatedRouteController.php` | ~600 | Admin dedicated route + payout management |
| `UrbanGoodzAdminController.php` | Variable | Command center — payment center, order anywhere |

### D. Key Model Reference

```
UrbanGoodzDedicatedRoute ──hasMany──> UrbanGoodzRoutePackage
                                       └──hasMany──> UrbanGoodzPackageScan
                                       └──hasMany──> UrbanGoodzMedicalCustodyLog
                                       └──hasMany──> UrbanGoodzDriverEarning
                                       └──hasOne───> UrbanGoodzRouteOptimizationStop
                                       └──belongsTo──> UrbanGoodzRouteBatch
                                                        └──belongsTo──> DeliveryMan

UrbanGoodzDedicatedRoute
  └──hasMany──> UrbanGoodzRouteBatch
  └──hasMany──> UrbanGoodzRouteAssignment ──belongsTo──> DeliveryMan
  └──belongsTo──> DeliveryMan (driver)
  └──hasMany──> UrbanGoodzDriverEarning
  └──hasMany──> UrbanGoodzClientInvoice
```

---

*End of Phase 2 planning document. No code was modified during this audit.*
