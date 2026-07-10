# P7 — Driver Smoke Test Checklist

Applies to the driver app consuming the Session 2 backend APIs (all under `auth:delivery_man`, prefix `urban-goodz/driver`). Base URL: `https://<host>/api/v1/urban-goodz/driver`.

> Auth: every request must send the driver bearer/token accepted by the `delivery_man` guard.

## A. Authentication
- [ ] Driver login succeeds and returns a valid `delivery_man` token.
- [ ] Unauthenticated calls to the endpoints below return 401.

## B. Business Courier (P1)
- [ ] `GET business-jobs` → assigned jobs list visible with counts.
- [ ] Tap a job → `GET business-jobs/{jobId}` opens detail.
- [ ] `POST business-jobs/{jobId}/accept` hits correct endpoint; status updates.
- [ ] `POST business-jobs/{jobId}/start` hits correct endpoint.
- [ ] `POST business-jobs/{jobId}/pickup` hits correct endpoint.
- [ ] `POST business-jobs/{jobId}/delivery` hits correct endpoint.
- [ ] `POST business-jobs/{jobId}/proof-pickup` submission path works (HTTPS proof URL or file).
- [ ] `POST business-jobs/{jobId}/proof-delivery` submission path works.
- [ ] `POST business-jobs/{jobId}/exception` submission path works.

## C. Capability Profile (P2)
- [ ] `GET capability-profile` loads.
- [ ] `GET capability-summary` loads.
- [ ] `POST capability-profile/vehicle` saves vehicle type / vehicle_id.
- [ ] `POST capability-profile/cargo` saves capacity flags.
- [ ] `POST capability-profile/zones` saves preferred zones.
- [ ] `POST capability-profile/work-types` saves work types.
- [ ] `POST capability-profile/tags` saves capability tags (allowlisted).
- [ ] `POST capability-profile/availability` saves availability prefs.
- [ ] Invalid vehicle type / tag / work type is rejected (422).

## D. Job Discovery (P3)
- [ ] `GET job-discovery` list loads (read-only).
- [ ] `GET job-discovery/summary` loads counts.
- [ ] `GET job-discovery/{type}/{id}` opens detail for `business_courier` / `package_pool` / `dedicated_route`.
- [ ] `can_claim=false` is displayed/handled; no claim button enabled.
- [ ] If a claim button already exists in UI, it is hidden/disabled for discovery jobs.

## E. Dispatch Notifications (P4)
- [ ] `GET dispatch-notifications` inbox loads.
- [ ] `GET dispatch-notifications/unread-count` loads.
- [ ] `POST dispatch-notifications/{notificationId}/read` marks read; `read_at` set.
- [ ] `POST dispatch-notifications/read-all` marks all read.
- [ ] `POST dispatch-notifications/{notificationId}/dismiss` dismisses.
- [ ] Invalid/other-driver `notificationId` returns 404.

## F. Compliance / Safety checks
- [ ] Age verification flag (`age_verification_required` / `age_restricted_review`) visible as a **review warning only**.
- [ ] Medical review flag (`medical_review_required`) visible as a **review warning only**.
- [ ] **No `admin_notes`** displayed anywhere in any response.
- [ ] **No payment/payout fields** displayed (rate/final/quote/authorized amounts, payout, commission).
- [ ] `proof_url` requires `https://` (enforced server-side).

## G. Backend contract stability (already verified)
- [ ] All 25 Session 2 routes resolve under `auth:delivery_man` (confirmed via `route:list`).
- [ ] P5 producer emits all 7 notification types on the correct assignment/exception events.

## Out of scope (do not test / do not build)
- Push / WebSocket delivery.
- Driver claim / auto-assignment.
- Mobile UI redesign.
- Payout/payment changes.
- AI execution changes.
