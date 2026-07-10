# P7 — Driver App API Integration Report

Phase: 4B-P7 (Deadline mode · connect existing driver backend to app flows)
Repo scope: Laravel backend only. **No Flutter/mobile app exists in this repository** (no `*.dart`, `pubspec.yaml`, or `lib/` present). Therefore app-side wiring (endpoint constants, API services, response models, screen binding) must be performed in the separate driver-app repository using the contracts below.

## Backend API readiness — VERIFIED

| Phase | Group | Routes registered | Status |
|-------|-------|-------------------|--------|
| P1 Business Courier | `GET/POST .../business-jobs...` | 9 (list, detail, accept, start, pickup, delivery, proof-pickup, proof-delivery, exception) | ✅ Registered |
| P2 Capability | `GET/POST .../capability-profile...` | 8 (profile, summary, vehicle, cargo, zones, work-types, tags, availability) | ✅ Registered |
| P3 Job Discovery | `GET .../job-discovery...` | 3 (list, summary, detail) | ✅ Registered |
| P4 Dispatch Inbox | `GET/POST .../dispatch-notifications...` | 5 (list, unread-count, read, read-all, dismiss) | ✅ Registered |

All routes are under `auth:delivery_man` (prefix `urban-goodz/driver`). Verified via `php artisan route:list`.

## P5 / P6 producer hooks — VERIFIED
Backend notifications are produced for all 7 required types:
`business_courier_assigned`, `business_courier_updated`, `dedicated_route_assigned`, `package_exception`, `proof_required`, `age_verification_required`, `medical_review_required`.
(Dedupe + sensitive-field protection validated in P6 audit/report.)

## APIs "wired" (backend contract ready for app consumption)
All accepted Session 2 APIs above are live and contract-stable. The driver app only needs to call them.

## APIs "not wired" (because no app code in this repo)
- Flutter endpoint constants (must be added in app repo).
- API service/repository methods (app repo).
- Response models/parsers (app repo).
- Screen bindings for dashboard / notifications / capability / discovery (app repo).
- Claim-button handling (`can_claim=false`) — discovery jobs are read-only by design; app must hide/disable any claim affordance.

## Files changed in THIS repo
None (no code/migration changes). P7 is documentation + readiness verification only.

## Screens affected (in the driver app repo — not here)
- Driver dashboard: assigned business jobs count (`business-jobs`), available discovery count (`job-discovery/summary`), unread dispatch count (`dispatch-notifications/unread-count`).
- Notification screen: `dispatch-notifications` inbox + `unread-count` + `read`/`read-all`/`dismiss`.
- Capability/profile screen: `capability-profile` + `capability-summary` + the 6 update POSTs.
- Job/discovery screen: `job-discovery` + `job-discovery/summary` + `job-discovery/{type}/{id}`.
- Business courier screens: `business-jobs` + detail + 7 action POSTs.

## Known gaps
- **App repo not in this workspace** — actual screen wiring cannot be done here; this deliverable provides the contract + smoke checklist for the app team.
- **DB-backed behavioral tests still blocked** in this environment (no reachable DB); P4/P5 row creation verified statically (see P6 report).
- P3 `job-discovery` detail support types: `business_courier`, `package_pool`, `dedicated_route` (allowlisted; `order_anywhere` not in detail).
- Deferred notification types `package_pool_available`, `dedicated_route_available`, `order_anywhere_count_available` are NOT produced (aggregate/PII reasons — by design).

## What can be tested now (backend)
- All 25 routes resolve and require `auth:delivery_man`.
- P1–P4 security/regression suites pass (22 tests).
- P5 producer logic validated (allowlist, dedupe, driver scoping).
- Endpoint contracts are stable for app integration.

## What still needs backend data
- Live driver auth token to exercise endpoints end-to-end.
- Seeded `delivery_men` + assigned jobs/routes to populate discovery/inbox with real rows.
- Push/WebSocket delivery is explicitly out of scope (P4/P5 are pull/inbox only).

## PM recommendation
**Accept P7 backend readiness.** Backend is integration-complete and contract-stable. Hand the two docs (this report + smoke checklist) plus `P7_API_CONTRACT.md` to the driver-app team to perform the actual Flutter wiring in the app repository. Do not deploy. Do not start P8 unless instructed.
