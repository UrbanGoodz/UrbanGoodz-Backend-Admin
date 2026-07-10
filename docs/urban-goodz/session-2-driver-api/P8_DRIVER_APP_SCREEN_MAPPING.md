# P8 DRIVER APP — SCREEN MAPPING
**URBAN GOODZ — SESSION 2 — PHASE 4B-P8**

Maps each driver-app screen to the backend endpoints it should consume. Use this together
with `P8_DRIVER_APP_API_CONTRACT.md`.

Base URL: `https://admin.urbangoodzdelivery.com/api/v1/urban-goodz/driver`
Auth: `Authorization: Bearer <driver_token>`

---

## 1. Driver Dashboard
| Element | Endpoint |
|---------|----------|
| Assigned job count badges | `GET /business-jobs` → `counts` |
| Quick links / capability snapshot | `GET /capability-summary` |
| Discovery teaser counts | `GET /job-discovery/summary` |
| Unread notification badge | `GET /dispatch-notifications/unread-count` |
| High-priority alert banner | `GET /dispatch-notifications` → filter `priority == high` |

Notes: dashboard is a read + launch surface. No mutations except optionally
`POST /dispatch-notifications/read-all` on "mark all read".

---

## 2. Business Courier Jobs List / Detail
| Element | Endpoint |
|---------|----------|
| Jobs list | `GET /business-jobs` |
| Job detail | `GET /business-jobs/{jobId}` |
| Status badge | `job.status` (state machine in contract §7) |
| Pickup/dropoff map data | `job.pickup.*`, `job.dropoff.*` |
| Requirements panel | `job.requirements.*` |
| Rate display (info only) | `job.pricing.rate_offered` (NOT payout) |
| Proof status indicators | `job.proof.pickup_proof_submitted`, `delivery_proof_submitted` |
| Exception indicator | `job.exception.has_exception` |

---

## 3. Business Courier Action Buttons
| Button | Endpoint | Guard |
|--------|----------|-------|
| Accept | `POST /business-jobs/{jobId}/accept` | status `assigned` |
| Start / En route | `POST /business-jobs/{jobId}/start` | `assigned`/`driver_en_route` |
| Mark Pickup | `POST /business-jobs/{jobId}/pickup` | `driver_en_route`/`picked_up` |
| Mark Delivery | `POST /business-jobs/{jobId}/delivery` | `picked_up`/`in_transit`/`delayed`/`delivered` |
| Submit Pickup Proof | `POST /business-jobs/{jobId}/proof-pickup` | job accessible |
| Submit Delivery Proof | `POST /business-jobs/{jobId}/proof-delivery` | job accessible |
| Report Exception | `POST /business-jobs/{jobId}/exception` | active status |

UI must enable/disable buttons based on `job.status`. After each action, refresh the job
from `GET /business-jobs/{jobId}`.

---

## 4. Capability / Profile Screen
| Element | Endpoint |
|---------|----------|
| View profile | `GET /capability-profile` |
| Quick summary | `GET /capability-summary` |
| Edit vehicle | `POST /capability-profile/vehicle` |
| Edit cargo capacity | `POST /capability-profile/cargo` |
| Edit zones | `POST /capability-profile/zones` |
| Edit work types | `POST /capability-profile/work-types` |
| Edit tags | `POST /capability-profile/tags` |
| Edit availability | `POST /capability-profile/availability` |

Use `allowed_values` from `GET /capability-profile` to populate dropdowns/validators.
After each POST, re-read `GET /capability-profile` to refresh UI.

---

## 5. Job Discovery Screen
| Element | Endpoint |
|---------|----------|
| Discovery list | `GET /job-discovery` |
| Discovery summary cards | `GET /job-discovery/summary` |
| Item detail | `GET /job-discovery/{type}/{id}` |

**Read-only screen.** Every row has `can_claim: false`. Render as informational cards only.
No "Claim"/"Accept" button on this screen. Show `match_reasons` as highlights and
`review_flags` as warnings.

---

## 6. Dispatch Notification Inbox
| Element | Endpoint |
|---------|----------|
| Inbox list | `GET /dispatch-notifications` |
| Unread badge | `GET /dispatch-notifications/unread-count` |
| Open / mark read | `POST /dispatch-notifications/{notificationId}/read` |
| Mark all read | `POST /dispatch-notifications/read-all` |
| Dismiss | `POST /dispatch-notifications/{notificationId}/dismiss` |

Tapping a notification with `job_id` + `job_type` should deep-link to the relevant job
(`GET /business-jobs/{jobId}`) or discovery detail. `can_open`/`can_dismiss` are always true.

---

## 7. Proof Submission
| Screen | Endpoint |
|--------|----------|
| Pickup proof form | `POST /business-jobs/{jobId}/proof-pickup` |
| Delivery proof form | `POST /business-jobs/{jobId}/proof-delivery` |

Support both file upload (`multipart/form-data`) and pre-hosted `https://` URL.
Optional `notes` (max 500). On success show returned proof URL.

---

## 8. Exception Submission
| Screen | Endpoint |
|--------|----------|
| Exception form | `POST /business-jobs/{jobId}/exception` |

Required `reason` (max 1000), optional `notes` (max 500). On submit, status becomes
`delayed` and backend fires a package-exception notification (P5/P6 producer hook).

---

## 9. Age / Medical Review Warnings
Surfaced from multiple endpoints:

| Source | Field | Action in app |
|--------|-------|---------------|
| `GET /job-discovery` rows | `age_restricted`, `review_flags: ["age_restricted_review"]` | Show age-restricted badge; block claim (no claim exists anyway) |
| `GET /job-discovery` rows | `requires_medical_training` + driver lacks training | Show "medical training required" warning |
| `GET /dispatch-notifications` | `type: age_verification_required` / `medical_review_required`, `priority: high`, `review_flags` | High-priority banner; deep-link to job; warn before any action |
| `GET /capability-summary` | `dispatch_matching.can_handle_medical_courier` | Disable medical actions if false |

The app must render these warnings visibly and prevent the driver from proceeding with
actions that require training/clearance they do not have.

---

## 10. Endpoint → Screen Matrix (quick reference)

| # | Endpoint | Screen(s) |
|---|----------|-----------|
| 1 | GET /business-jobs | 2, 3 |
| 2 | GET /business-jobs/{jobId} | 2, 3, 6 |
| 3 | POST /business-jobs/{jobId}/accept | 3 |
| 4 | POST /business-jobs/{jobId}/start | 3 |
| 5 | POST /business-jobs/{jobId}/pickup | 3 |
| 6 | POST /business-jobs/{jobId}/delivery | 3 |
| 7 | POST /business-jobs/{jobId}/proof-pickup | 7 |
| 8 | POST /business-jobs/{jobId}/proof-delivery | 7 |
| 9 | POST /business-jobs/{jobId}/exception | 8 |
| 10 | GET /capability-profile | 4 |
| 11 | GET /capability-summary | 1, 4, 9 |
| 12 | POST /capability-profile/vehicle | 4 |
| 13 | POST /capability-profile/cargo | 4 |
| 14 | POST /capability-profile/zones | 4 |
| 15 | POST /capability-profile/work-types | 4 |
| 16 | POST /capability-profile/tags | 4 |
| 17 | POST /capability-profile/availability | 4 |
| 18 | GET /job-discovery | 5 |
| 19 | GET /job-discovery/summary | 1, 5 |
| 20 | GET /job-discovery/{type}/{id} | 5, 6, 9 |
| 21 | GET /dispatch-notifications | 1, 6, 9 |
| 22 | GET /dispatch-notifications/unread-count | 1, 6 |
| 23 | POST /dispatch-notifications/{notificationId}/read | 6 |
| 24 | POST /dispatch-notifications/read-all | 1, 6 |
| 25 | POST /dispatch-notifications/{notificationId}/dismiss | 6 |

---

*End of screen mapping.*
