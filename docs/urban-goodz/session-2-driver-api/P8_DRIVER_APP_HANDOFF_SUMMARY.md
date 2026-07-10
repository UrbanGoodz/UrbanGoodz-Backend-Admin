# P8 DRIVER APP — HANDOFF SUMMARY
**URBAN GOODZ — SESSION 2 — PHASE 4B-P8 — DRIVER APP INTEGRATION HANDOFF PACKAGE**

---

## 1. What Backend Is Ready

The `AdminPanel_Update_V39` backend already provides **25 contract-stable driver endpoints**
registered under `auth:delivery_man` (prefix `api/v1/urban-goodz/driver`). All were verified
present via `php artisan route:list` (see §7). They cover:

- **Business Courier (9):** assigned jobs list/detail, accept/start/pickup/delivery state
  machine, pickup & delivery proof submission (file or https URL), exception reporting
  (which fires the P5/P6 notification producer hook).
- **Capability (8):** read profile/summary; update vehicle, cargo, zones, work-types, tags,
  availability. `allowed_values` enumerations are returned for client dropdowns.
- **Job Discovery (3):** read-only list/summary/detail of unassigned available work
  (business_courier / package_pool / dedicated_route). Every row returns `can_claim: false`.
- **Dispatch Notifications (5):** polled inbox over existing `user_notifications`, with
  unread count, mark-read, mark-all-read, dismiss.

Auth is driver bearer token (`Authorization: Bearer <driver_token>`). No new backend code
was written in this phase; this phase is docs/contracts/test collection only.

---

## 2. What the App Repo Must Implement

1. API client + endpoint constants for the 25 routes (see `P8_DRIVER_APP_IMPLEMENTATION_CHECKLIST.md` §1–2).
2. JSON models/parsers for jobs, capability, discovery, notifications, errors (§3).
3. Screens mapped in `P8_DRIVER_APP_SCREEN_MAPPING.md`: dashboard, business courier
   list/detail, action buttons, capability, discovery, notification inbox, proof, exception,
   age/medical warnings.
4. Secure driver-token storage + `401` re-login handling (checklist §6).
5. Status-state-machine aware action buttons (assigned→accept→start→pickup→delivery).
6. Poll-based notification refresh (no push/WebSocket).
7. Visibility-only discovery UI honoring `can_claim = false`.
8. Prominent age-restricted / medical-review warnings.

---

## 3. What Is Out of Scope

- Driver **claim** / self-assign from discovery (no endpoint exists; `can_claim` always false).
- **Auto-assignment** logic.
- **Push notifications / WebSocket** delivery (poll only).
- **Payout / payment** changes or displays (these 25 endpoints carry no payout; `rate_offered` is reference only).
- AI execution logic.
- Business listing activation.
- Any migration, `route:cache`, or deployment (backend stays on `route:clear` posture due to
  known duplicate route-name conflict `admin.rental.provider.status`).

---

## 4. Known Blockers

| # | Blocker | Impact | Owner |
|---|---------|--------|-------|
| B1 | Test driver token + seed data not yet staged | App smoke test (checklist §9) cannot run end-to-end | Backend/PM (see `P8_DRIVER_TEST_DATA_REQUIREMENTS.md`) |
| B2 | No push channel in contract | Notifications rely on polling; app must implement poll timer | App team (expected) |
| B3 | `can_claim` permanently false | Discovery is informational only; assignment is admin/dispatch-driven | By design |
| B4 | Backend `route:cache` forbidden | Deployment must use `route:clear`; not an app concern but note for backend CI | Backend/DevOps |

No blockers prevent the app team from starting client implementation against the contract.

---

## 5. PM Recommendation

1. **Approve this handoff package** and pass it to the driver-app repo team.
2. **Stage the test data** in `P8_DRIVER_TEST_DATA_REQUIREMENTS.md` in a staging environment
   before the app team begins smoke testing.
3. Treat `can_claim = false` and poll-only notifications as **accepted design constraints**,
   not gaps — they match the P7 decision (no claim/auto-assignment, no push this phase).
4. Keep P8 strictly a handoff; do **not** expand scope to claim/auto-assignment/push until a
   future, explicitly approved phase.

---

## 6. Package Contents

| File | Purpose |
|------|---------|
| `P8_DRIVER_APP_API_CONTRACT.md` | Full 25-endpoint contract: base URL, auth, bodies, responses, errors, safety notes |
| `P8_DRIVER_APP_SCREEN_MAPPING.md` | Endpoint → app screen mapping, state machine, matrix |
| `P8_DRIVER_APP_IMPLEMENTATION_CHECKLIST.md` | Constants, services, models, UI states, errors, auth, `can_claim`/payout handling, smoke steps |
| `P8_DRIVER_TEST_DATA_REQUIREMENTS.md` | Seed data the backend must stage for app smoke testing |
| `urban-goodz-driver-api.postman_collection.json` | Postman/Thunder collection, env vars `base_url` + `driver_token`, 25 routes, sample bodies |
| `P8_DRIVER_APP_HANDOFF_SUMMARY.md` | This file |

All under `docs/urban-goodz/session-2-driver-api/`.

---

*End of handoff summary.*
