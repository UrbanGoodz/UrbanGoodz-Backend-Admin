# LOGISTICS WORKFLOW MATRIX
## UrbanGoodz Complete Logistics Operating Model
### E2E Certification Reference — Created 2026-07-19

---

## WORKFLOW TYPE A: SINGLE DELIVERY

| Field | Value |
|-------|-------|
| **WORKFLOW** | Single Delivery (standard order) |
| **SOURCE ROLE** | Customer |
| **CREATION SCREEN** | Customer App → Store → Item → Cart → Checkout |
| **API ENDPOINT** | `POST /api/v1/customer/order/place` |
| **BACKEND ROUTE** | `customer.order.place` |
| **CONTROLLER** | `Api\V1\OrderController::place_order` |
| **SERVICE** | `CentralLogics\OrderLogic` + `Helpers` |
| **TABLE** | `orders`, `order_details`, `carts` |
| **RECORD ID** | `orders.id` |
| **ASSIGNMENT METHOD** | Admin assigns DM via `Admin\OrderController::dm_assign` or auto-assign |
| **PRICING METHOD** | `Config::get('extra_charge')` + delivery fee + tax |
| **DRIVER PAY METHOD** | `delivery_men_earnings` table on completion |
| **NOTIFICATION FLOW** | `Helpers::send_push_notif_to_device()` → customer (order placed), vendor (new order), DM (assignment) |
| **FINAL STATUS** | `order_status = 4` (delivered) |
| **RESULT** | Customer tracking → POD → payment → ledger |

### Gate Status
- [x] Route exists: `POST /api/v1/customer/order/place`
- [x] Controller exists: `OrderController::place_order`
- [ ] Full chain verified end-to-end with persistent record

---

## WORKFLOW TYPE B: MULTI-STOP DELIVERY

| Field | Value |
|-------|-------|
| **WORKFLOW** | Multi-Stop Delivery |
| **SOURCE ROLE** | Business / Vendor / Dispatcher |
| **CREATION SCREEN** | Admin Business Courier Dashboard or Driver App |
| **API ENDPOINT** | `POST /api/v1/urban-goodz/business-courier/jobs` |
| **BACKEND ROUTE** | `admin.urban-goodz.business-courier.store` |
| **CONTROLLER** | `Admin\UrbanGoodz\UrbanGoodzBusinessClientController` |
| **SERVICE** | `UrbanGoodzDriverDispatchNotificationService` |
| **TABLE** | `urban_goodz_business_courier_jobs`, `urban_goodz_business_courier_packages`, `urban_goodz_business_courier_stops` |
| **RECORD ID** | `business_courier_jobs.id` (route), `business_courier_stops.id` (stop), `business_courier_packages.id` (package) |
| **ASSIGNMENT METHOD** | Admin/Dispatcher assigns via `jobAssignDriver()` |
| **PRICING METHOD** | Per-stop pricing + route-level surcharge |
| **DRIVER PAY METHOD** | `delivery_men_earnings` on route completion |
| **NOTIFICATION FLOW** | `notifyBusinessCourierAssigned()` → DM; `notifyBusinessCourierUpdated()` → DM on status change |
| **FINAL STATUS** | Route status = `completed`; stop statuses = `delivered` / `failed` / `returned` |
| **RESULT** | Package-level tracking, stop-level status, partial completion, failed/return stops |

### Required Certification Tests
- [ ] 5-stop route: all delivered
- [ ] 10-stop route: mixed delivered/failed
- [ ] Duplicate address stops
- [ ] Bad address → exception
- [ ] Stop removed after planning
- [ ] Stop added after planning
- [ ] Locked priority stop
- [ ] Failed delivery → exception handling
- [ ] Return package → traceable back to origin
- [ ] Route cancellation mid-transit
- [ ] Driver reassignment mid-route
- [ ] Partial completion (some stops delivered, some not)
- [ ] Each stop has unique ID persisting across all systems

---

## WORKFLOW TYPE C: DEDICATED ROUTE

| Field | Value |
|-------|-------|
| **WORKFLOW** | Dedicated Route (manifest-based, scheduled) |
| **SOURCE ROLE** | Admin / Dispatcher |
| **CREATION SCREEN** | Admin Dedicated Route Dashboard |
| **API ENDPOINT** | `POST /api/v1/urban-goodz/dedicated-routes` |
| **BACKEND ROUTE** | `admin.urban-goodz.dedicated-routes.store` |
| **CONTROLLER** | `Admin\UrbanGoodz\UrbanGoodzDedicatedRouteController` |
| **SERVICE** | `UrbanGoodzDriverDispatchNotificationService::notifyDedicatedRouteAssigned()` |
| **TABLE** | `urban_goodz_dedicated_routes`, `urban_goodz_dedicated_route_stops` |
| **RECORD ID** | `dedicated_routes.id` |
| **ASSIGNMENT METHOD** | Admin assigns via `assignDriver()` |
| **PRICING METHOD** | Fixed route payout or configured per-stop |
| **DRIVER PAY METHOD** | Fixed salary or per-route completion |
| **NOTIFICATION FLOW** | `notifyDedicatedRouteAssigned()` → DM (includes age/medical verification if needed) |
| **FINAL STATUS** | Route status = `completed` |
| **RESULT** | Manifest-based intake → fixed route → warehouse/lab/fulfillment → completion |

### Required Fields
- [ ] Start/end location
- [ ] Scheduled recurrence (daily/weekly/custom)
- [ ] Package count and manifest
- [ ] Fixed or dynamic payout
- [ ] Return handling

---

## WORKFLOW TYPE D: BUSINESS COURIER ROUTE

| Field | Value |
|-------|-------|
| **WORKFLOW** | Business Courier Route (business-created, multi-stop, package pool) |
| **SOURCE ROLE** | Business Client |
| **CREATION SCREEN** | Business Portal / Admin Business Courier Dashboard |
| **API ENDPOINT** | `POST /api/v1/urban-goodz/business-courier/jobs` |
| **BACKEND ROUTE** | `admin.urban-goodz.business-courier.store` |
| **CONTROLLER** | `Admin\UrbanGoodz\UrbanGoodzBusinessClientController` |
| **SERVICE** | `UrbanGoodzDriverDispatchNotificationService` |
| **TABLE** | `urban_goodz_business_courier_jobs`, `urban_goodz_business_courier_packages`, `urban_goodz_business_courier_stops`, `urban_goodz_business_clients` |
| **RECORD ID** | `business_courier_jobs.id` |
| **ASSIGNMENT METHOD** | Dispatcher/Admin assigns driver |
| **PRICING METHOD** | Per-package + per-stop + route surcharge |
| **DRIVER PAY METHOD** | Per-route or per-stop completion |
| **NOTIFICATION FLOW** | Business: job created; Dispatcher: assignment; DM: pickup/delivery notifications |
| **FINAL STATUS** | Route = `completed`; packages = `delivered`/`returned` |
| **RESULT** | Business creates route → attaches packages → Dispatcher reviews → Driver assigned → manifest → scans → delivery → billing → invoice |

### Required Certification Tests
- [ ] Business creates route with packages
- [ ] Per-stop package type assignment
- [ ] Scheduled pickup and delivery windows
- [ ] Route edit preserves audit history
- [ ] Route cancel with proper notifications
- [ ] Billing and invoice generation
- [ ] Employee/business scoping (only own routes visible)

---

## WORKFLOW TYPE E: MEDICAL COURIER

| Field | Value |
|-------|-------|
| **WORKFLOW** | Medical Courier (specimens, blood, pharma, equipment, records) |
| **SOURCE ROLE** | Medical Facility / Admin / Dispatcher |
| **CREATION SCREEN** | Admin Medical Courier Dashboard |
| **API ENDPOINT** | `POST /api/v1/urban-goodz/medical-courier/jobs` |
| **BACKEND ROUTE** | `admin.urban-goodz.medical-courier.store` |
| **CONTROLLER** | `Admin\UrbanGoodz\UrbanGoodzMedicalCourierController` |
| **SERVICE** | `UrbanGoodzMedicalCourierService` (if exists) |
| **TABLE** | `urban_goodz_medical_courier_jobs` (if exists) |
| **RECORD ID** | `medical_courier_jobs.id` |
| **ASSIGNMENT METHOD** | Dispatcher matches qualified Drivers (medical certification required) |
| **PRICING METHOD** | Priority-based (STAT > routine) + distance + handling requirements |
| **DRIVER PAY METHOD** | Premium rate for medical certifications |
| **NOTIFICATION FLOW** | Custody transfer events → all parties; exception → Admin alert |
| **FINAL STATUS** | `completed` with chain-of-custody record |
| **RESULT** | Request → qualified Driver match → pickup identity check → custody scan → transport → delivery identity check → receiving signature → custody completion |

### Required Fields
- [ ] Requesting organization
- [ ] Pickup/delivery contact
- [ ] Package/specimen type (STAT, blood, pharma, equipment, records)
- [ ] Handling requirements
- [ ] Temperature requirements
- [ ] Priority (STAT / urgent / routine)
- [ ] Pickup/delivery deadline
- [ ] Chain-of-custody requirement
- [ ] Return requirement
- [ ] Exception procedure

### Required Certification Tests
- [ ] Only qualified Drivers eligible
- [ ] Custody events cannot be silently edited
- [ ] Timestamps and actors recorded for every custody transfer
- [ ] Private medical data minimized (no diagnosis stored)
- [ ] Failed custody event triggers Admin alert
- [ ] Return linked to original request
- [ ] Medical exception → Admin notification

### Gap Status
- [ ] `UrbanGoodzMedicalCourierService` exists at `app/Services/UrbanGoodz/UrbanGoodzMedicalCourierService.php`
- [ ] Medical courier controller exists
- [ ] Medical courier database tables exist
- [ ] Driver medical certification model exists
- [ ] Chain-of-custody audit trail exists
- [ ] Temperature/handling requirement fields exist

---

## WORKFLOW TYPE F: LOAD BOARD FREIGHT / LOGISTICS LOAD

| Field | Value |
|-------|-------|
| **WORKFLOW** | Load Board Freight (sourced or internally created) |
| **SOURCE ROLE** | Dispatcher / Admin / External Source |
| **CREATION SCREEN** | Admin Load Sourcing Dashboard |
| **API ENDPOINT** | `POST /api/v1/urban-goodz/load-sourcing/loads` |
| **BACKEND ROUTE** | `admin.urban-goodz.load-sourcing.store` |
| **CONTROLLER** | `Admin\UrbanGoodz\UrbanGoodzLoadSourcingController` |
| **SERVICE** | `UrbanGoodz\LoadSource\LoadSourcingService` |
| **TABLE** | `urban_goodz_loads`, `urban_goodz_load_assignments` |
| **RECORD ID** | `loads.id` |
| **ASSIGNMENT METHOD** | Dispatcher reviews AI recommendation → assigns Driver |
| **PRICING METHOD** | Market rate + lane pricing + vehicle type + weight/dimensions |
| **DRIVER PAY METHOD** | Settlement on delivery confirmation |
| **NOTIFICATION FLOW** | Dispatcher: new load; DM: offer; DM: acceptance; status updates; settlement |
| **FINAL STATUS** | `delivered` + `settled` |
| **RESULT** | Source → sync → normalize → dedup → risk validate → AI recommend → Dispatcher review → match → assign → execute → POD → settlement |

### Load Source Classification

| Source | Classification |
|--------|---------------|
| Internal/Admin-created | `LIVE_VERIFIED` |
| Partner feed (with API) | `CREDENTIAL_REQUIRED` |
| Email ingestion | `EMAIL_INGESTION_ONLY` |
| Manual import | `MANUAL_IMPORT_ONLY` |
| External API (no credential) | `PARTNER_APPROVAL_REQUIRED` |
| Disconnected source | `DISABLED` |

### AI Recommendation Inputs
- Vehicle type and capacity
- Load dimensions/weight
- Pickup/delivery location
- Deadhead distance
- Lane preference
- Driver availability and schedule
- Credentials/compliance status
- Route history
- Acceptance history
- Minimum payout threshold
- Driver preference
- Service area
- Source quality score
- Fraud/risk indicators

### Required Certification Tests
- [ ] Load source → sync → normalize → dedup
- [ ] AI recommendation with persisted explanation and score components
- [ ] Dispatcher review → Driver match → assignment
- [ ] Driver acceptance → execution → POD → settlement
- [ ] External source integration (if any configured)
- [ ] Load board listing with search/filter

---

## CROSS-CUTTING: DISPATCHER OPERATIONS

| Field | Value |
|-------|-------|
| **WORKFLOW** | Dispatcher E2E Operations |
| **ROLE** | Dispatcher (restricted portal or Business Portal role) |
| **LOGIN** | Admin panel with `dispatch` module access |
| **PERMISSIONS** | Load view, Driver assignment, status tracking, communication, settlement view |
| **RESTRICTIONS** | No unrestricted Admin access; scoped to assigned loads only |

### Dispatcher Lifecycle
1. Dispatcher logs in
2. Views available loads (internal + sourced)
3. Reviews AI recommendations
4. Selects and assigns Driver
5. Driver receives offer
6. Driver accepts
7. Dispatcher tracks progress
8. Load completes
9. Commission posts
10. Audit log records all actions

---

## CROSS-CUTTING: NOTIFICATION REQUIREMENTS

Every logistics notification must contain:
- [ ] Correct recipient
- [ ] Correct role
- [ ] Correct record ID
- [ ] Correct deep link
- [ ] Correct status
- [ ] Channel status (push/email/sms)
- [ ] Provider result
- [ ] Read/unread state

### Notification Events by Workflow

| Workflow | Event | Recipient | Channel |
|----------|-------|-----------|---------|
| Single Delivery | Order placed | Vendor | Push + In-App |
| Single Delivery | DM assigned | Customer, DM | Push |
| Single Delivery | Delivery complete | Customer, Vendor | Push |
| Multi-Stop | Route created | DM | Push + Dispatch Inbox |
| Multi-Stop | Stop arrival | Customer | Push |
| Multi-Stop | Stop delivered | Business, Customer | Push |
| Multi-Stop | Stop failed | Dispatcher, Business | Push + Alert |
| Multi-Stop | Return initiated | Dispatcher, Business | Push |
| Dedicated Route | Route assigned | DM | Push + Dispatch Inbox |
| Dedicated Route | Manifest ready | DM | Dispatch Inbox |
| Business Courier | Job created | Dispatcher | Push + Alert |
| Business Courier | Driver assigned | DM, Business | Push |
| Business Courier | Package scanned | Business | Push |
| Medical Courier | Pickup scheduled | DM, Facility | Push |
| Medical Courier | Custody transfer | All parties | Push + Audit |
| Medical Courier | Delivery confirmed | Facility, Dispatcher | Push |
| Load Board | New load | Dispatcher | Push + Alert |
| Load Board | Driver assigned | DM | Push |
| Load Board | Load complete | Dispatcher, Admin | Push |
| Load Board | Settlement posted | DM, Dispatcher | Push |

---

## CROSS-CUTTING: PAYMENT ALLOCATION

All logistics workflows must reconcile:
- [ ] Amount charged
- [ ] Tax
- [ ] Processing fee
- [ ] Vendor/merchant amount
- [ ] Driver payout
- [ ] Dispatcher commission
- [ ] UrbanGoodz margin
- [ ] Refund/adjustment (if any)
- [ ] Policy version
- [ ] Override actor

---

## EVIDENCE STANDARD

For every workflow record:

| Field | Required |
|-------|----------|
| WORKFLOW | Yes |
| SOURCE ROLE | Yes |
| SOURCE RECORD ID | Yes — same ID across all systems |
| SOURCE SCREEN | Yes |
| API | Yes |
| DATABASE TABLE | Yes |
| ADMIN PAGE | Yes |
| DESTINATION ROLE | Yes |
| DESTINATION SCREEN | Yes |
| NOTIFICATION ID | Yes |
| PAYMENT ID | Yes |
| LEDGER ID | Yes |
| FINAL STATUS | Yes |
| LIVE RESULT | Yes (runtime test) |
| PHYSICAL DEVICE RESULT | Yes (ZT42268MG6) |
| COMMIT | Yes |
| DEPLOYED COMMIT | Yes |
| BLOCKER | Document if any |

---

## GAP ANALYSIS SUMMARY

| Workflow Type | Routes | Controller | Service | DB Tables | Flutter UI | Admin UI | Status |
|--------------|--------|------------|---------|-----------|------------|----------|--------|
| A: Single Delivery | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | READY FOR E2E TEST |
| B: Multi-Stop | ✅ | ✅ | ✅ | ✅ | Partial | ✅ | NEEDS CERTIFICATION |
| C: Dedicated Route | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | NEEDS DRIVER APP UI |
| D: Business Courier | ✅ | ✅ | ✅ | ✅ | ✅ (Driver) | ✅ | NEEDS CERTIFICATION |
| E: Medical Courier | ? | ? | ✅ | ? | ❌ | ? | NEEDS AUDIT |
| F: Load Board | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | NEEDS CERTIFICATION |
| Dispatcher Ops | ✅ | ✅ | N/A | N/A | ❌ | ✅ | NEEDS ROLE SCOPING |
