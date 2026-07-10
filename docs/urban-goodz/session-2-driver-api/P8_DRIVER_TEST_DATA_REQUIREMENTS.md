# P8 DRIVER APP — TEST DATA REQUIREMENTS
**URBAN GOODZ — SESSION 2 — PHASE 4B-P8**

Data the backend team must stage so the app team can run the smoke test in
`P8_DRIVER_APP_IMPLEMENTATION_CHECKLIST.md` (§9). No live/customer data. Staging only.

---

## 1. Required Test Driver
- A `delivery_man` account (`DeliveryMan` model) with a valid auth token
  (`auth:delivery_man` guard).
- Must have a linked `vehicle` row OR a `vehicle_type` set.
- Capability seeds:
  - `preferred_zones` e.g. `["Houston, TX"]`
  - `preferred_work_types` include `business_courier`
  - `capability_tags` include `business_courier`
  - `available_for_business_courier = true`
  - `has_medical_courier_training = false` (so medical review warnings are demonstrable)
  - `has_liftgate` set to a known value (used by `liftgate_match` in discovery)
- Provide the app team a **non-secret** test token (or a way to mint one in staging).

---

## 2. Assigned Business Courier Job
- One `UrbanGoodzBusinessClientJob` with:
  - `assigned_delivery_man_id` = test driver id
  - `status = assigned`
  - Non-null `pickupLocation` and `dropoffLocation` (city/state for zone matching)
  - `vehicle_type_needed` matching driver's vehicle (to trigger `vehicle_type_match`)
  - `rate_offered` + `currency` (display-only)
  - `driver_accepted_at` null initially
- Used by: `GET /business-jobs`, detail, accept/start/pickup/delivery/proof/exception.

---

## 3. Unassigned Business Courier Job (for discovery)
- One `UrbanGoodzBusinessClientJob` with:
  - `assigned_delivery_man_id = NULL`
  - `status` ∈ `submitted|under_review|accepted|quoted|quote_accepted`
  - In a zone matching the test driver's `preferred_zones` (so `match_reasons` populate)
- Used by: `GET /job-discovery`, `GET /job-discovery/summary`, detail `business_courier/{id}`.

---

## 4. Package Pool Row (for discovery)
- One `UrbanGoodzRoutePackage` with:
  - `dedicated_route_id = NULL`
  - `status` ∈ `pending|pending_review|ready_for_route`
  - `pickup_address` / `dropoff_address` populated
  - `dropoff_city` / `dropoff_state` for zone name
- Used by: `package_pool` discovery rows.

---

## 5. Dedicated Route Row (for discovery)
- One `UrbanGoodzDedicatedRoute` with:
  - `assigned_driver_id = NULL`
  - `status` ∈ `pending|pending_review|approved`
  - `pickup_location` (zone), `end_location`, `vehicle_type_required`, `total_packages`
- Used by: `dedicated_route` discovery rows.

---

## 6. Notification Rows
- At least 2 `user_notifications` rows where `delivery_man_id` = test driver id, with `data`
  JSON containing:
  - One `type = business_courier_assigned` (job_id pointing to assigned job above),
    `read_at` null.
  - One `type = age_verification_required` (job_type `package_pool`, job_id pointing to the
    age-restricted package from §8), `read_at` null, so `priority = high`.
- Used by: `GET /dispatch-notifications`, `unread-count`, read/dismiss flows.

---

## 7. Age-Restricted Test Row
- A package (`UrbanGoodzRoutePackage`) OR dedicated route with:
  - `age_restricted = true` OR `requires_id_verification = true` (package), or
    `contains_age_restricted_items = true` (route).
  - Status eligible for discovery (see §4/§5).
- Must surface as `age_restricted: true` + `review_flags: ["age_restricted_review"]` in
  `GET /job-discovery` and as a high-priority `age_verification_required` notification.

---

## 8. Medical Review Test Row
- A business courier / package / route with `job_type` or `route_type` or `priority` =
  `medical_courier` (or `medical`), and `assigned_delivery_man_id = NULL` + eligible status.
- Test driver has `has_medical_courier_training = false` → must surface
  `requires_medical_training: true` + `review_flags: ["medical_training_required"]` and
  increment `medical_courier_review_only` in `GET /job-discovery/summary`.

---

## 9. Proof URL Requirements
- App team need a way to test both proof paths:
  - **File upload**: any dev endpoint/host accepting `jpg|jpeg|png|pdf|webp` ≤ 10 MB.
  - **URL**: backend requires `proof_url` to be a URL starting with `https://`.
    Provide a staging `https://` asset URL (e.g. a placeholder image on the staging CDN)
    so the URL path can be exercised without real PII.
- Do NOT use production/customer proof URLs or secrets.

---

## 10. Staging Boundaries
- All rows above are **test/seed** data, isolated from live customers.
- No migration runs required; data is inserted via seeders / admin UI in staging.
- Backend keeps `route:clear` posture (no `route:cache`) due to known duplicate
  route-name conflict (`admin.rental.provider.status`).

---

*End of test data requirements.*
