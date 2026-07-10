# P8 DRIVER APP — IMPLEMENTATION CHECKLIST
**URBAN GOODZ — SESSION 2 — PHASE 4B-P8**
For the separate driver-app repository. Backend is already built; app team implements the client.

---

## 1. Endpoint Constants to Add

Create a single source of truth for the 25 endpoints (e.g. `lib/api/urban_goodz_endpoints.dart`
or equivalent). Suggested naming:

```
BASE:            {{base_url}}/api/v1/urban-goodz/driver
BUSINESS_JOBS:           GET    /business-jobs
BUSINESS_JOB_DETAIL:     GET    /business-jobs/{jobId}
BUSINESS_JOB_ACCEPT:     POST   /business-jobs/{jobId}/accept
BUSINESS_JOB_START:      POST   /business-jobs/{jobId}/start
BUSINESS_JOB_PICKUP:     POST   /business-jobs/{jobId}/pickup
BUSINESS_JOB_DELIVERY:   POST   /business-jobs/{jobId}/delivery
BUSINESS_JOB_PROOF_PICKUP:    POST /business-jobs/{jobId}/proof-pickup
BUSINESS_JOB_PROOF_DELIVERY:  POST /business-jobs/{jobId}/proof-delivery
BUSINESS_JOB_EXCEPTION:  POST   /business-jobs/{jobId}/exception

CAPABILITY_PROFILE:      GET    /capability-profile
CAPABILITY_SUMMARY:      GET    /capability-summary
CAPABILITY_VEHICLE:      POST   /capability-profile/vehicle
CAPABILITY_CARGO:        POST   /capability-profile/cargo
CAPABILITY_ZONES:        POST   /capability-profile/zones
CAPABILITY_WORK_TYPES:   POST   /capability-profile/work-types
CAPABILITY_TAGS:         POST   /capability-profile/tags
CAPABILITY_AVAILABILITY: POST   /capability-profile/availability

JOB_DISCOVERY:           GET    /job-discovery
JOB_DISCOVERY_SUMMARY:   GET    /job-discovery/summary
JOB_DISCOVERY_DETAIL:    GET    /job-discovery/{type}/{id}

DISPATCH_NOTIFICATIONS:        GET    /dispatch-notifications
DISPATCH_UNREAD_COUNT:         GET    /dispatch-notifications/unread-count
DISPATCH_READ:                 POST   /dispatch-notifications/{notificationId}/read
DISPATCH_READ_ALL:             POST   /dispatch-notifications/read-all
DISPATCH_DISMISS:              POST   /dispatch-notifications/{notificationId}/dismiss
```

Use `{{base_url}}` from environment config; never hardcode host in code.

---

## 2. Repository / Service Methods to Add

Group by feature. Recommended service classes:

- `BusinessCourierService`
  - `getAssignedJobs()` → `GET /business-jobs`
  - `getJob(jobId)` → `GET /business-jobs/{jobId}`
  - `acceptJob(jobId)` → `POST .../accept`
  - `startJob(jobId)` → `POST .../start`
  - `markPickup(jobId)` → `POST .../pickup`
  - `markDelivery(jobId)` → `POST .../delivery`
  - `submitPickupProof(jobId, file|url, notes)` → `POST .../proof-pickup`
  - `submitDeliveryProof(jobId, file|url, notes)` → `POST .../proof-delivery`
  - `reportException(jobId, reason, notes)` → `POST .../exception`
- `CapabilityService`
  - `getProfile()`, `getSummary()`
  - `updateVehicle(...)`, `updateCargo(...)`, `updateZones(...)`, `updateWorkTypes(...)`,
    `updateTags(...)`, `updateAvailability(...)`
- `JobDiscoveryService`
  - `list()`, `summary()`, `detail(type, id)`
- `DispatchNotificationService`
  - `list()`, `unreadCount()`, `markRead(id)`, `markAllRead()`, `dismiss(id)`

Each method must attach `Authorization: Bearer <token>` and parse `errors` on 422.

---

## 3. Models / Parsers to Add

Parse the JSON shapes from `P8_DRIVER_APP_API_CONTRACT.md`. Suggested models:

- `BusinessCourierJob` (mirror the `jobDetailResponse` object: pickup/dropoff/requirements/
  pricing/driver/proof/timeline/driver_notes/exception)
- `BusinessCourierJobList` (`jobs`, `counts`)
- `CapabilityProfile` + `CapabilitySummary` + `AllowedValues`
- `DiscoveryItem` (`job_type`, `job_id`, `can_claim`, `age_restricted`, `match_reasons`,
  `review_flags`, `requires_medical_training`)
- `DiscoveryList` (`discovery`, `counts`)
- `DiscoverySummary` (`summary`, `match_stats`)
- `DispatchNotification` (`type`, `priority`, `requires_action`, `review_flags`, `status`,
  `read_at`, `can_open`, `can_dismiss`)
- `DispatchInbox` (`notifications`, `unread_count`, `total`)
- `ApiError` (`message`, `errors` map)

---

## 4. UI States Required

For every screen implement at least: `loading`, `empty`, `data`, `error`, `refreshing`.
Specifically:

- Business courier list: empty state when `counts.total == 0`.
- Action buttons: disabled/enabled per `job.status` state machine (contract §7).
- Proof forms: file picker + URL toggle; progress indicator during upload.
- Discovery: read-only cards; `can_claim == false` → no actionable button.
- Notifications: unread badge; pull-to-refresh poll; high-priority banner.
- Capability: dropdown options sourced from `allowed_values` (not hardcoded).

---

## 5. Error Handling

- `401` → token expired/invalid → force re-login / token refresh.
- `404` → not owned / not available → show "not found" and refresh list (item may have moved).
- `422` → show field errors from `errors` object; for proof, show URL/file validation message.
- Network failure → retry with backoff; keep last good cache for list views.
- Never assume a 2xx; always validate expected keys exist (backend may add fields).

---

## 6. Auth Token Handling

- Store driver token securely (platform secure storage, e.g. flutter_secure_storage).
- Attach `Authorization: Bearer <token>` on every request.
- On `401`, clear token and route to login.
- `driver_token` is the delivery_man guard token — distinct from any admin/PM token.

---

## 7. `can_claim = false` Handling (CRITICAL)

- `GET /job-discovery` and `GET /job-discovery/{type}/{id}` always return `can_claim: false`.
- The app MUST NOT render any "Claim", "Accept", or "Book" action on discovery items.
- Discovery is informational/visibility only. Assigned work arrives via
  `GET /business-jobs` (after admin/dispatch assigns it).
- Do not build any claim/auto-assignment feature — out of scope and not supported by backend.

---

## 8. No `admin_notes` / Payment / Payout Display

- Business-job `pricing.rate_offered` is **display-only reference**, not driver payout.
- Do NOT show any payout/payment/earnings figure derived from these 25 endpoints.
- There is no `admin_notes` field in these responses; do not invent one.
- Driver compensation lives in separate endpoints (`/earnings`, `/payout-history`) — out of scope.

---

## 9. Smoke Test Steps (app-side, against backend)

1. Login as test driver → obtain `driver_token`.
2. `GET /business-jobs` → expect ≥1 assigned job (test data requirement).
3. Open job detail `GET /business-jobs/{jobId}` → verify pickup/dropoff/requirements render.
4. `POST .../accept` → status reflects accepted; then `start`, `pickup`, `delivery` in order.
5. `POST .../proof-pickup` (file) → proof URL returned; `proof.pickup_proof_submitted == true`.
6. `POST .../exception` → status `delayed`; verify a `package_exception` notification appears
   after refresh of `GET /dispatch-notifications`.
7. `GET /capability-profile` → render; `POST .../zones` then re-`GET` to confirm persistence.
8. `GET /job-discovery` → confirm every row `can_claim == false`; confirm age-restricted row
   shows `age_restricted: true` + `review_flags`.
9. `GET /dispatch-notifications/unread-count` → matches inbox; `read-all` then count == 0.
10. Negative: calling `delivery` before `pickup` → expect `404` (status guard).

---

## 10. Out-of-Scope (do NOT build)

- Driver claim / self-assign from discovery.
- Auto-assignment logic.
- Push notifications / WebSocket delivery (poll only).
- Payout/payment changes or displays.
- AI execution logic.
- Business listing activation.
- Any backend migration / route:cache / deployment.

---

*End of implementation checklist.*
