# P6 — Driver Notification Hook Coverage Audit

Scope: Validate P4 (driver dispatch notification inbox) + P5 (producer hooks) and audit whether all current driver-assignment / exception paths are covered by producer hooks.

## Business courier assignment paths found

| # | Location | Path | Covered by P5? |
|---|----------|------|----------------|
| A1 | `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzBusinessClientController.php::jobAssignDriver` (line 512) | Admin assigns `assigned_delivery_man_id` to a business courier job | YES — `notifyBusinessCourierAssigned` |
| A2 | `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzBusinessClientController.php::jobUpdateStatus` (line 478) | Admin sets status `assigned` + `assigned_delivery_man_id` | YES — `notifyBusinessCourierAssigned` (assignment transition) / `notifyBusinessCourierUpdated` (subsequent) |

## Business courier status-update paths found

- `jobUpdateStatus` covers all driver-relevant status transitions for an already-assigned job → produces `business_courier_updated` with per-status dedupe key.
- P1 driver-side mutating endpoints (`acceptJob`, `startJob`, `markPickup`, `markDelivery`, proof endpoints) operate on jobs already assigned to the authenticated driver and do NOT change `assigned_delivery_man_id`; they are not assignment events, so no new notification is required (status updates already covered by the admin `jobUpdateStatus` path).

## Dedicated route assignment paths found

| # | Location | Path | Covered by P5? |
|---|----------|------|----------------|
| R1 | `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzDedicatedRouteController.php::assignDriver` (line 131) | Admin assigns `assigned_driver_id` to a dedicated route | YES — `notifyDedicatedRouteAssigned` (+ `age_verification_required` / `medical_review_required` when applicable) |
| R2 | `app/Services/AiCopilotService.php::autoDispatchRoute` (line 528) | AI Ops auto-assigns `assigned_driver_id` to a low-risk route | NO — AI execution logic intentionally NOT modified (PM rule: do not modify AI execution logic) |

## Package / route exception paths found

| # | Location | Path | Covered by P5? |
|---|----------|------|----------------|
| E1 | `app/Http/Controllers/Api/UrbanGoodzDriverBusinessCourierController.php::reportException` (line 354) | Driver reports exception on assigned business courier job | YES — `notifyPackageException` |
| E2 | `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzDedicatedRouteController.php::packageUpdateStatus` (lines 290/304) | Admin marks a dedicated-route package failed / sets `exception_reason` | NO — different model (`UrbanGoodzRoutePackage`); out of P5 produced-types scope |
| E3 | `app/Http/Controllers/Api/UrbanGoodzDriverApiController.php::scanException` (line 361) | Driver reports exception on a dedicated-route package via scan | NO — different model; driver API (P1) is a locked/accepted system |

## Order Anywhere assignment / update paths found

| # | Location | Path | Covered by P5? |
|---|----------|------|----------------|
| O1 | `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzAdminController.php::orderAnywhereAssign` (line 131) | Admin assigns `assigned_delivery_man_id` on `OrderAnywhereRequest` | NO — deferred. `OrderAnywhereRequest` carries PII (customer name/phone/email) and payment internals; aggregate `order_anywhere_count_available` was intentionally deferred in P5 to avoid driver spam and PII/payment exposure |

## AI Ops / future dispatch assignment paths found

- `AiCopilotService::autoDispatchRoute` (R2) — AI auto-dispatch. Not covered by design (AI execution must remain untouched). If AI-driven assignment notifications are later desired, add the producer call inside this method in a dedicated, explicitly-approved change — not as part of P6.

## Coverage summary

- Covered (active, canonical admin/driver assignment + driver exception): A1, A2, R1, E1.
- Not covered (and why):
  - R2 — AI Ops auto-dispatch (do not modify AI execution).
  - O1 — Order Anywhere assignment (PII/payment; aggregate type deferred).
  - E2 — Dedicated-route package exception, admin (different model; outside P5 produced types).
  - E3 — Dedicated-route package exception, driver scan (different model; locked P1 driver API).

## Duplicate-hook analysis

- A1 and A2 can both fire `business_courier_assigned` for the same job/driver, but both use the identical dedupe key `business_courier_assigned:{jobId}:{driverId}`, so `createForDriver` suppresses the duplicate. No duplicate-row bug.
- `proof_required` and `medical_review_required` / `age_verification_required` each use distinct dedupe keys, so re-assignment does not spam.

## PM recommendation for additional hooks

- No change required for current active assignment/exception paths — coverage is complete and deduplicated.
- If desired later (separate approval): add `notifyRoutePackageException` for E2/E3 (dedicated-route package exceptions) and an Order Anywhere count-only aggregate notification for O1. These are explicitly out of P5/P6 scope and must not be added as part of this hardening phase.
