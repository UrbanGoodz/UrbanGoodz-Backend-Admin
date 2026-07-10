# P7 — Session 2 Driver API Contract

All endpoints are under `auth:delivery_man`, prefix `api/v1/urban-goodz/driver`.
Auth: driver token accepted by the `delivery_man` guard (typically `Authorization: Bearer <token>` or the existing `auth_token` scheme used by the legacy driver API).

## P1 — Business Courier Driver API
| Method | Path | Purpose |
|--------|------|---------|
| GET | `business-jobs` | List jobs assigned to the authenticated driver (with counts). |
| GET | `business-jobs/{jobId}` | Job detail (404 if not assigned to driver). |
| POST | `business-jobs/{jobId}/accept` | Driver accepts (sets `driver_accepted_at`). |
| POST | `business-jobs/{jobId}/start` | Mark `driver_en_route`. |
| POST | `business-jobs/{jobId}/pickup` | Mark `picked_up`. |
| POST | `business-jobs/{jobId}/delivery` | Mark `delivered`. |
| POST | `business-jobs/{jobId}/proof-pickup` | Upload/URL proof (HTTPS required for URL). |
| POST | `business-jobs/{jobId}/proof-delivery` | Upload/URL proof (HTTPS required for URL). |
| POST | `business-jobs/{jobId}/exception` | Report exception (reason, notes). |

Notes: driver-scoped only; `admin_notes` never returned; `proof_url` must be `https://`.

## P2 — Driver Capability API
| Method | Path | Purpose |
|--------|------|---------|
| GET | `capability-profile` | Full profile + normalized summary + `allowed_values`. |
| GET | `capability-summary` | Normalized capability summary only. |
| POST | `capability-profile/vehicle` | `vehicle_type` (allowlisted), `vehicle_id`. |
| POST | `capability-profile/cargo` | `cargo_capacity_notes`, `max_package_count` (0–10000), `max_weight_lbs` (0–100000), booleans. |
| POST | `capability-profile/zones` | `preferred_zones` (array, max 100). |
| POST | `capability-profile/work-types` | `preferred_work_types` (allowlisted, max 50). |
| POST | `capability-profile/tags` | `capability_tags` (allowlisted, max 50). |
| POST | `capability-profile/availability` | `availability_preference` + 4 availability booleans. |

Allowlists: vehicle types, capability tags, work types, availability preferences (see `UrbanGoodzDriverCapabilityController` constants). No `driver_id` accepted.

## P3 — Job Discovery API (read-only)
| Method | Path | Purpose |
|--------|------|---------|
| GET | `job-discovery` | List available work (business_courier, package_pool, dedicated_route). |
| GET | `job-discovery/summary` | Counts + match stats. |
| GET | `job-discovery/{type}/{id}` | Detail for `{type}` ∈ {business_courier, package_pool, dedicated_route}; 404 otherwise. |

Every row: `job_type, job_id, title, status, zone_name, addresses, estimated_package_count, vehicle_type_required, requires_medical_training, age_restricted, match_reasons[], review_flags[], can_view=true, can_claim=false`. No claim endpoint; discovery is read-only.

## P4 — Dispatch Notification Inbox
| Method | Path | Purpose |
|--------|------|---------|
| GET | `dispatch-notifications` | List driver notifications (read/dismiss state in `data` JSON). |
| GET | `dispatch-notifications/unread-count` | Unread count. |
| POST | `dispatch-notifications/{notificationId}/read` | Mark read (404 if not owned). |
| POST | `dispatch-notifications/read-all` | Mark all read. |
| POST | `dispatch-notifications/{notificationId}/dismiss` | Dismiss (404 if not owned). |

Row shape: `id, type, title, body, job_type, job_id, status, priority, requires_action, review_flags[], created_at, read_at, can_open, can_dismiss`. `notificationId` must be integer; inaccessible → 404.

## P5 Producer Types (emitted by backend on events)
`business_courier_assigned`, `business_courier_updated`, `dedicated_route_assigned`, `package_exception`, `proof_required`, `age_verification_required`, `medical_review_required`.
Deferred (not produced): `package_pool_available`, `dedicated_route_available`, `order_anywhere_count_available`.

## Cross-cutting safety
- All routes `auth:delivery_man`; no `driver_id` from public input.
- No `admin_notes`, no payment/payout/customer-PII fields in any driver response.
- Age/medical surfaced as `review_flags` only (no compliance bypass).
- No push/WebSocket; inbox is pull-based.
