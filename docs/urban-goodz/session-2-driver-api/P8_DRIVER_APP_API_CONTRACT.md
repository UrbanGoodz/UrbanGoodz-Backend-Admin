# P8 DRIVER APP — API CONTRACT
**URBAN GOODZ — SESSION 2 — PHASE 4B-P8**
**Driver App Integration Handoff Package**

---

## 0. Purpose

This document is the authoritative API contract for the **separate driver-app repository**.
It describes the 25 backend driver endpoints already built, registered, and contract-stable
in the `AdminPanel_Update_V39` backend. The app team uses this to wire the driver app
without building any backend behavior.

> **This phase is docs/contracts/test-collection only.**
> No backend behavior changes. No claim. No auto-assignment. No push/WebSocket. No payout changes.

---

## 1. Base URL Pattern

```
https://admin.urbangoodzdelivery.com/api/v1/urban-goodz/driver
```

| Part | Value |
|------|-------|
| Scheme | `https` |
| Host | `admin.urbangoodzdelivery.com` |
| API prefix | `/api/v1` |
| Module prefix | `/urban-goodz/driver` |
| Full example | `https://admin.urbangoodzdelivery.com/api/v1/urban-goodz/driver/business-jobs` |

For local/staging, swap the host only; the path stays identical.

---

## 2. Authentication

All 25 endpoints require a **driver (delivery_man) bearer token**.

```
Authorization: Bearer <driver_token>
```

| Field | Required | Notes |
|-------|----------|-------|
| `Authorization` | yes | `Bearer ` + Sanctum-style driver token issued by the delivery_man auth guard |
| `Accept` | recommended | `application/json` |
| `Content-Type` | POST only | `application/json` (or `multipart/form-data` for proof file uploads) |

- Guard: `auth:delivery_man`
- Unauthenticated request → `401 Unauthenticated driver`
- The token is the driver's own token, **not** an admin/PM token.

---

## 3. Global Response Envelope

Success responses are `application/json`. Error responses use standard HTTP status codes.

### Standard error shape

```json
{
  "message": "Validation failed",
  "errors": {
    "proof_url": ["The proof url must start with https://."]
  }
}
```

| HTTP | Meaning | Common cause |
|------|---------|--------------|
| `401` | Unauthenticated driver | Missing/expired `Authorization` bearer token |
| `404` | Not found / not owned by driver | job id not assigned to this driver, or notification not owned |
| `422` | Validation failed | Request body fails rules (see `errors` object) |
| `500` | Server error | Backend bug — report to backend team |

Specific error examples are given per endpoint below.

---

## 4. Endpoint Index (25 endpoints)

### Business Courier (9)
1. `GET  /business-jobs`
2. `GET  /business-jobs/{jobId}`
3. `POST /business-jobs/{jobId}/accept`
4. `POST /business-jobs/{jobId}/start`
5. `POST /business-jobs/{jobId}/pickup`
6. `POST /business-jobs/{jobId}/delivery`
7. `POST /business-jobs/{jobId}/proof-pickup`
8. `POST /business-jobs/{jobId}/proof-delivery`
9. `POST /business-jobs/{jobId}/exception`

### Capability (8)
10. `GET  /capability-profile`
11. `GET  /capability-summary`
12. `POST /capability-profile/vehicle`
13. `POST /capability-profile/cargo`
14. `POST /capability-profile/zones`
15. `POST /capability-profile/work-types`
16. `POST /capability-profile/tags`
17. `POST /capability-profile/availability`

### Job Discovery (3)
18. `GET  /job-discovery`
19. `GET  /job-discovery/summary`
20. `GET  /job-discovery/{type}/{id}`

### Dispatch Notifications (5)
21. `GET  /dispatch-notifications`
22. `GET  /dispatch-notifications/unread-count`
23. `POST /dispatch-notifications/{notificationId}/read`
24. `POST /dispatch-notifications/read-all`
25. `POST /dispatch-notifications/{notificationId}/dismiss`

---

## 5. Endpoint Detail

### 1. GET /business-jobs
List business courier jobs assigned to the authenticated driver.

**Query params:** none
**Body:** none

**Response 200**
```json
{
  "jobs": [
    {
      "job_id": 12,
      "job_number": "BC-2026-00012",
      "business_client_id": 3,
      "business_client_name": "Acme Logistics LLC",
      "job_type": "business_courier",
      "status": "assigned",
      "description": "Pallet of documents",
      "reference_number": "REF-9981",
      "po_number": "PO-4412",
      "pickup": {
        "location_id": 7,
        "name": "Acme Dock A",
        "address": "1100 Industrial Pkwy",
        "city": "Houston",
        "state": "TX",
        "postal_code": "77002",
        "latitude": 29.7604,
        "longitude": -95.3698,
        "contact_name": "Dock Supervisor",
        "contact_phone": "7135550100",
        "pickup_instructions": "Ring buzzer 2",
        "pickup_earliest": "2026-07-10T08:00:00+00:00",
        "pickup_latest": "2026-07-10T12:00:00+00:00"
      },
      "dropoff": {
        "location_id": 9,
        "name": "Acme Front Desk",
        "address": "200 Main St",
        "city": "Houston",
        "state": "TX",
        "postal_code": "77002",
        "latitude": 29.7589,
        "longitude": -95.3677,
        "contact_name": "Receiving",
        "contact_phone": "7135550199",
        "delivery_instructions": "Leave at desk",
        "delivery_deadline": "2026-07-10T17:00:00+00:00"
      },
      "requirements": {
        "vehicle_type_needed": "cargo_van",
        "needs_liftgate": false,
        "needs_dock": true,
        "special_handling": "fragile",
        "load_type": "pallet",
        "weight": 120,
        "weight_unit": "lbs",
        "temperature_requirement": null,
        "specimen_type": null,
        "urgency_level": "standard",
        "courier_certification_required": false,
        "chain_of_custody_required": false
      },
      "pricing": {
        "rate_offered": "45.00",
        "currency": "USD"
      },
      "driver": {
        "assigned_delivery_man_id": 21,
        "assigned_at": "2026-07-09T14:00:00+00:00",
        "driver_accepted_at": null
      },
      "proof": {
        "proof_of_pickup": null,
        "proof_of_delivery": null,
        "pickup_proof_submitted": false,
        "delivery_proof_submitted": false
      },
      "timeline": {
        "assigned_at": "2026-07-09T14:00:00+00:00",
        "driver_accepted_at": null,
        "picked_up_at": null,
        "delivered_at": null,
        "created_at": "2026-07-09T13:30:00+00:00",
        "updated_at": "2026-07-09T14:00:00+00:00"
      },
      "driver_notes": null,
      "exception": {
        "has_exception": false,
        "reason": null,
        "reported_at": null
      }
    }
  ],
  "counts": {
    "total": 1,
    "assigned": 1,
    "active": 0,
    "completed": 0
  }
}
```

**Error 401** — missing token.
**Error 404** — none for list (returns empty array if no jobs).

---

### 2. GET /business-jobs/{jobId}
Single assigned job detail (same `job` object shape as above, wrapped in `{ "job": {...} }`).

**Path param:** `jobId` (integer, required)
**Body:** none

**Response 200**
```json
{ "job": { "job_id": 12, "job_number": "BC-2026-00012", "...": "..." } }
```

**Error 404** — job not found or not assigned to this driver:
```json
{ "message": "No query results for model [App\\Models\\UrbanGoodzBusinessClientJob] 999" }
```

---

### 3. POST /business-jobs/{jobId}/accept
Accept an assigned job. Only valid when `status == assigned`. Sets `driver_accepted_at`.

**Path param:** `jobId`
**Body:** none (empty POST)

**Response 200**
```json
{ "message": "Job accepted successfully", "job": { "...": "..." } }
```

**Error 404** — job not in `assigned` status or not owned.
**Error 422** — none (no body).

---

### 4. POST /business-jobs/{jobId}/start
Mark driver en route. Valid from `assigned` or `driver_en_route`. Sets `status = driver_en_route`.

**Path param:** `jobId`
**Body:** none

**Response 200**
```json
{ "message": "Job started", "job": { "...": "..." } }
```

**Error 404** — not in allowed status.

---

### 5. POST /business-jobs/{jobId}/pickup
Mark pickup complete. Valid from `driver_en_route` or `picked_up`. Sets `status = picked_up`, `picked_up_at`.

**Path param:** `jobId`
**Body:** none

**Response 200**
```json
{ "message": "Pickup completed", "job": { "...": "..." } }
```

---

### 6. POST /business-jobs/{jobId}/delivery
Mark delivery complete. Valid from `picked_up`, `in_transit`, `delayed`, `delivered`. Sets `status = delivered`, `delivered_at`.

**Path param:** `jobId`
**Body:** none

**Response 200**
```json
{ "message": "Delivery completed", "job": { "...": "..." } }
```

---

### 7. POST /business-jobs/{jobId}/proof-pickup
Submit proof of pickup (file upload OR secure URL). Updates `proof_of_pickup`.

**Path param:** `jobId`
**Content-Type:** `multipart/form-data` (file) or `application/json` (url)

**Body (file upload)**
```
proof: <file jpg|jpeg|png|pdf|webp, max 10240 KB>
notes: <optional string max 500>
```

**Body (URL)**
```json
{
  "proof_url": "https://cdn.example.com/proofs/pickup-12.jpg",
  "notes": "Loaded at dock A"
}
```

Validation: `proof` is `required_without:proof_url`; `proof_url` is `required_without:proof`, must be URL starting with `https://`.

**Response 200**
```json
{ "message": "Pickup proof submitted", "proof_of_pickup": "https://admin.urbangoodzdelivery.com/storage/urban-goodz/business-jobs/proofs/pickup/abc.jpg" }
```

**Error 422**
```json
{ "errors": { "proof_url": ["The proof url must start with https://."] } }
```

---

### 8. POST /business-jobs/{jobId}/proof-delivery
Submit proof of delivery. Same rules as #7 but `proof_of_delivery`.

**Body:** same as #7.

**Response 200**
```json
{ "message": "Delivery proof submitted", "proof_of_delivery": "https://admin.urbangoodzdelivery.com/storage/urban-goodz/business-jobs/proofs/delivery/xyz.jpg" }
```

---

### 9. POST /business-jobs/{jobId}/exception
Report an exception. Valid from `assigned`, `driver_en_route`, `picked_up`, `in_transit`, `delayed`. Sets `exception_reason`, `exception_reported_at`, `status = delayed`, and fires a package-exception dispatch notification (P5/P6 producer hook).

**Path param:** `jobId`
**Body**
```json
{
  "reason": "Vehicle breakdown, cannot complete route",
  "notes": "Towed, ETA unknown"
}
```

Validation: `reason` required, string max 1000; `notes` optional string max 500.

**Response 200**
```json
{ "message": "Exception reported", "job": { "exception": { "has_exception": true, "reason": "Vehicle breakdown...", "reported_at": "2026-07-09T15:30:00+00:00" }, "...": "..." } }
```

**Error 422** — missing reason.

---

### 10. GET /capability-profile
Full driver capability/vehicle profile.

**Response 200**
```json
{
  "profile": {
    "driver_id": 21,
    "vehicle_type": "cargo_van",
    "vehicle_id": 5,
    "cargo_capacity_notes": "12ft cargo area",
    "max_package_count": 40,
    "max_weight_lbs": 1500,
    "has_cargo_space": true,
    "has_cooler_bag": false,
    "has_medical_courier_training": false,
    "has_liftgate": true,
    "preferred_zones": ["Houston, TX", "Austin, TX"],
    "preferred_work_types": ["business_courier", "package_routes"],
    "capability_tags": ["business_courier", "cargo_van"],
    "availability_preference": "standard",
    "available_for_business_courier": true,
    "available_for_package_routes": true,
    "available_for_order_anywhere": false,
    "available_for_medical_courier": false
  },
  "vehicle": { "id": 5, "type": "Cargo Van", "status": "active" },
  "normalized_capability_summary": { "...": "..." },
  "allowed_values": {
    "vehicle_types": ["car","suv","cargo_van","pickup_truck","box_truck","van","bike","motorcycle"],
    "capability_tags": ["food_delivery","retail_delivery","business_courier","package_routes","medical_courier","order_anywhere","cargo_van","pickup_truck","box_truck","car","suv","event_runner","rental_support"],
    "preferred_work_types": ["food_delivery","retail_delivery","business_courier","package_routes","medical_courier","order_anywhere","event_runner","rental_support"],
    "availability_preferences": ["standard","weekdays","weekends","evenings","overnight","on_demand"]
  }
}
```

---

### 11. GET /capability-summary
Lightweight normalized capability summary (subset, no `profile`/`allowed_values` wrappers).

**Response 200**
```json
{
  "normalized_capability_summary": {
    "driver_id": 21,
    "vehicle": { "vehicle_id": 5, "vehicle_type": "cargo_van", "vehicle_name": "Cargo Van", "vehicle_status": "active" },
    "capacity": {
      "cargo_capacity_notes": "12ft cargo area",
      "max_package_count": 40,
      "max_weight_lbs": 1500,
      "has_cargo_space": true,
      "has_cooler_bag": false,
      "has_medical_courier_training": false,
      "has_liftgate": true
    },
    "preferences": {
      "preferred_zones": ["Houston, TX"],
      "preferred_work_types": ["business_courier"],
      "availability_preference": "standard"
    },
    "availability": {
      "available_for_business_courier": true,
      "available_for_package_routes": true,
      "available_for_order_anywhere": false,
      "available_for_medical_courier": false
    },
    "capability_tags": ["business_courier","cargo_van"],
    "dispatch_matching": {
      "can_handle_food_delivery": false,
      "can_handle_retail_delivery": false,
      "can_handle_business_courier": true,
      "can_handle_package_routes": true,
      "can_handle_order_anywhere": false,
      "can_handle_medical_courier": false,
      "has_structured_vehicle_type": true,
      "has_capacity_profile": true
    }
  }
}
```

---

### 12. POST /capability-profile/vehicle
Update vehicle type and/or linked vehicle id.

**Body**
```json
{
  "vehicle_type": "box_truck",
  "vehicle_id": 8
}
```
Validation: `vehicle_type` nullable, in allowed list; `vehicle_id` nullable integer, exists `d_m_vehicles.id`.

**Response 200** — `{ "message": "Vehicle profile updated", ...profilePayload }`

**Error 422** — invalid `vehicle_type`:
```json
{ "errors": { "vehicle_type": ["The selected vehicle type is invalid."] } }
```

---

### 13. POST /capability-profile/cargo
Update cargo capacity flags.

**Body**
```json
{
  "cargo_capacity_notes": "14ft box",
  "max_package_count": 60,
  "max_weight_lbs": 3000,
  "has_cargo_space": true,
  "has_cooler_bag": true,
  "has_medical_courier_training": false,
  "has_liftgate": true
}
```
Validation: all nullable; `max_package_count` 0–10000; `max_weight_lbs` 0–100000; booleans.

**Response 200** — `{ "message": "Cargo capacity updated", ...profilePayload }`

---

### 14. POST /capability-profile/zones
Replace preferred zones.

**Body**
```json
{ "preferred_zones": ["Houston, TX", "Austin, TX", "Dallas, TX"] }
```
Validation: `preferred_zones` required array max 100, each string max 100.

**Response 200** — `{ "message": "Preferred zones updated", ...profilePayload }`

**Error 422** — `preferred_zones` required.

---

### 15. POST /capability-profile/work-types
Replace preferred work types.

**Body**
```json
{ "preferred_work_types": ["business_courier", "package_routes"] }
```
Validation: required array max 50; each in `preferred_work_types` allowed list.

**Response 200** — `{ "message": "Preferred work types updated", ...profilePayload }`

---

### 16. POST /capability-profile/tags
Replace capability tags.

**Body**
```json
{ "capability_tags": ["business_courier", "cargo_van", "package_routes"] }
```
Validation: required array max 50; each in `capability_tags` allowed list.

**Response 200** — `{ "message": "Capability tags updated", ...profilePayload }`

---

### 17. POST /capability-profile/availability
Update availability preferences/flags.

**Body**
```json
{
  "availability_preference": "weekdays",
  "available_for_business_courier": true,
  "available_for_package_routes": true,
  "available_for_order_anywhere": false,
  "available_for_medical_courier": false
}
```
Validation: `availability_preference` nullable in list; flags nullable booleans.

**Response 200** — `{ "message": "Availability preferences updated", ...profilePayload }`

---

### 18. GET /job-discovery
Read-only discovery of **available** work the driver is NOT yet assigned to.

**Response 200**
```json
{
  "discovery": [
    {
      "job_type": "business_courier",
      "job_id": 15,
      "title": "BC-2026-00015 (business_courier)",
      "status": "submitted",
      "zone_id": null,
      "zone_name": "Houston, TX",
      "pickup_address": "1100 Industrial Pkwy",
      "dropoff_address": "200 Main St",
      "estimated_package_count": 1,
      "vehicle_type_required": "cargo_van",
      "requires_medical_training": false,
      "age_restricted": false,
      "match_reasons": ["preferred_zone_match", "vehicle_type_match"],
      "review_flags": [],
      "can_view": true,
      "can_claim": false
    },
    {
      "job_type": "package_pool",
      "job_id": 88,
      "title": "Package PKG-88",
      "status": "pending",
      "zone_id": null,
      "zone_name": "Austin, TX",
      "pickup_address": "5 Warehouse Rd",
      "dropoff_address": "9 Delivery Ln",
      "estimated_package_count": 1,
      "vehicle_type_required": null,
      "requires_medical_training": false,
      "age_restricted": true,
      "match_reasons": [],
      "review_flags": ["age_restricted_review"],
      "can_view": true,
      "can_claim": false
    },
    {
      "job_type": "dedicated_route",
      "job_id": 4,
      "title": "Route Daily South Loop",
      "status": "approved",
      "zone_id": null,
      "zone_name": "Houston, TX",
      "pickup_address": "Houston, TX",
      "dropoff_address": "Dallas, TX",
      "estimated_package_count": 25,
      "vehicle_type_required": "box_truck",
      "requires_medical_training": false,
      "age_restricted": false,
      "match_reasons": ["preferred_zone_match"],
      "review_flags": [],
      "can_view": true,
      "can_claim": false
    }
  ],
  "counts": {
    "total": 3,
    "business_courier": 1,
    "package_pool": 1,
    "dedicated_route": 1
  }
}
```

> **IMPORTANT:** Every discovery row returns `"can_claim": false`. The driver app must
> display available work as **read-only / informational only**. There is NO claim endpoint
> in this backend. Do not build claim/accept-from-discovery UI actions.

---

### 19. GET /job-discovery/summary
Aggregated counts for discovery dashboard.

**Response 200**
```json
{
  "summary": {
    "business_courier_available": 1,
    "package_pool_available": 1,
    "dedicated_routes_available": 1,
    "order_anywhere_available": 0,
    "medical_courier_review_only": 0
  },
  "match_stats": {
    "business_courier_matched": 1,
    "package_pool_matched": 0,
    "dedicated_routes_matched": 1
  }
}
```

---

### 20. GET /job-discovery/{type}/{id}
Single discovery item detail. `{type}` ∈ `business_courier | package_pool | dedicated_route`. `{id}` integer ≥ 1.

**Example:** `GET /job-discovery/package_pool/88`

**Response 200**
```json
{ "job": { "job_type": "package_pool", "job_id": 88, "age_restricted": true, "...": "..." } }
```

**Error 404** — invalid type or id not found/available:
```json
{ "message": "Not Found" }
```

---

### 21. GET /dispatch-notifications
Driver's dispatch notification inbox (over existing `user_notifications` rows for this driver). Dismissed items are hidden.

**Response 200**
```json
{
  "notifications": [
    {
      "id": 301,
      "type": "business_courier_assigned",
      "title": "New Business Courier Job",
      "body": "You have been assigned BC-2026-00012",
      "job_type": "business_courier",
      "job_id": 12,
      "status": "unread",
      "priority": "normal",
      "requires_action": true,
      "review_flags": [],
      "created_at": "2026-07-09T14:00:00+00:00",
      "read_at": null,
      "can_open": true,
      "can_dismiss": true
    },
    {
      "id": 305,
      "type": "age_verification_required",
      "title": "Age Verification Required",
      "body": "Package PKG-88 requires age verification",
      "job_type": "package_pool",
      "job_id": 88,
      "status": "unread",
      "priority": "high",
      "requires_action": true,
      "review_flags": ["age_restricted_review"],
      "created_at": "2026-07-09T14:10:00+00:00",
      "read_at": null,
      "can_open": true,
      "can_dismiss": true
    }
  ],
  "unread_count": 2,
  "total": 2
}
```

**Notification `type` values:** `business_courier_assigned`, `business_courier_updated`, `package_pool_available`, `dedicated_route_available`, `dedicated_route_assigned`, `package_exception`, `proof_required`, `age_verification_required`, `medical_review_required`, `order_anywhere_count_available`, or `notification` (fallback).
**High priority types:** `age_verification_required`, `medical_review_required`, `package_exception`, `proof_required`.

---

### 22. GET /dispatch-notifications/unread-count
Returns only the unread count.

**Response 200**
```json
{ "unread_count": 2 }
```

---

### 23. POST /dispatch-notifications/{notificationId}/read
Mark a single owned notification read.

**Path param:** `notificationId`
**Body:** none

**Response 200**
```json
{ "message": "Notification marked as read", "notification": { "id": 301, "status": "read", "read_at": "2026-07-09T16:00:00+00:00", "...": "..." } }
```

**Error 404** — not owned / not found.

---

### 24. POST /dispatch-notifications/read-all
Mark all visible (non-dismissed, unread) notifications read.

**Body:** none

**Response 200**
```json
{ "message": "All notifications marked as read" }
```

---

### 25. POST /dispatch-notifications/{notificationId}/dismiss
Soft-dismiss a notification (sets `dismissed_at`, hides from inbox).

**Path param:** `notificationId`
**Body:** none

**Response 200**
```json
{ "message": "Notification dismissed" }
```

**Error 404** — not owned / not found.

---

## 6. Safety Notes

1. **No claim endpoint.** Discovery endpoints are read-only. `can_claim` is always `false`.
   Do NOT implement any "accept/claim" action from discovery screens.
2. **No push / WebSocket delivery.** Notifications are polled via the `dispatch-notifications`
   endpoints. The app must poll (e.g. on dashboard open / pull-to-refresh / periodic timer).
   There is no backend push channel in this contract.
3. **No payout / payment fields.** The `pricing.rate_offered` on business-jobs is display-only
   and is NOT a payout. Do not show payout/payment/earnings amounts as driver-compensable
   from these endpoints. (Separate `payout-history` / `earnings` endpoints exist outside scope.)
4. **Age / medical review warnings.** Discovery rows and notifications can carry
   `age_restricted` / `review_flags` (`age_restricted_review`, `medical_review_required`).
   The app MUST surface these warnings prominently and block any action requiring training
   the driver lacks (`requires_medical_training` with `has_medical_courier_training = false`).
5. **Proof uploads must be `https://` URLs or file uploads** (jpg/jpeg/png/pdf/webp, ≤10 MB).
6. **Job ownership.** Job/notification ids are scoped to the authenticated driver. Cross-driver
   access returns 404. Do not build shared/global job views from these endpoints.
7. **Idempotency.** `accept`/`start`/`pickup`/`delivery` set status; calling out of sequence
   (e.g. `delivery` before `pickup`) returns 404 because the status guard fails. Enforce
   correct state machine in UI (assigned → accept → start/en_route → pickup → delivery).
8. **Do not cache routes.** Backend has a known duplicate route-name conflict
   (`admin.rental.provider.status`); the backend uses `route:clear`, never `route:cache`.
   App teams have no relation to this, but note it as a backend deployment constraint.

---

## 7. Status State Machine (business courier jobs)

```
assigned --accept--> driver_accepted (driver_accepted_at set)
assigned/driver_en_route --start--> driver_en_route
driver_en_route/picked_up --pickup--> picked_up (picked_up_at set)
picked_up/in_transit/delayed/delivered --delivery--> delivered (delivered_at set)
any active status --exception--> delayed (+ exception_reason, notification fired)
```

Proof endpoints (`proof-pickup`, `proof-delivery`) are callable while the job is accessible and
do not change the status machine; they only populate proof fields.

---

*End of API contract.*
