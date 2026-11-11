# Urban Goodz certification punch list

Single source of truth for everything **not fully resolved**. Updated whenever an item opens or closes.
Release decision today: **NOT READY FOR PRODUCTION**. Full reasoning and the path to CONDITIONALLY READY: `FINAL_CERTIFICATION_REPORT.md`.

Legend: `OWNER` needs a decision from you · `ENV` needs a device/browser/production access I do not have ·
`TODO` I can do next · `DONE` closed (kept briefly for traceability).

## A. Blocks release
| # | Item | Type | Notes |
|---|------|------|-------|
| A1 | Admin 419 fix not verified in production | ENV | Fix + tests are unit-level. Production needs `TRUSTED_PROXIES`, correct `APP_URL`, `SESSION_SECURE_COOKIE`, a session driver shared across servers. See `ADMIN_419_INCIDENT.md`. |
| A2 | Migrations must run in production | ENV | `2026_11_07_000001_make_payout_request_delivery_man_nullable` (vendor payouts fail without it), `2026_11_08_000001_add_refund_requested_to_orders_if_missing`, `2026_11_10_000001_add_recurring_parent_to_dedicated_routes` (recurring routes), `2026_11_10_000002_create_urban_goodz_notification_preferences`, plus all earlier ones. |
| A3 | Stranded helper rewards: decide whether the platform should ever handle that money | OWNER | Resolved to the honest state: the reward is agreed in the app and paid by the customer directly to the helper. The ledger row no longer claims a completed Stripe payment (`provider=none`, `not_collected`) and the API tells the app the reward is paid directly. If you want the platform to hold and pay out rewards, that is Stripe Connect work and a new decision. |
| A4 | No device / Playwright / Appium runs | ENV | A phone is connected to the owner's computer, but this cloud session cannot reach it. Remote Control IS on and the computer 'Urban-Goodz' lists folders UG, AdminPanel_Update_V39 and UrbanGoodz2026-Revised, but all three come back 'not_available' to cloud sessions: update the Claude desktop app on that computer (Windows needs a recent version) and re-check. Alternatively start a session on that computer (Claude desktop app > Settings > Claude Code > Remote Control, or run `claude remote-control` in the repo folder). That session can run adb + Flutter device tests. A ready checklist is in `DEVICE_TEST_RUNBOOK.md` (every changed behaviour with what to do and what passes). All app-level behaviour here is unit/feature-test verified only. |
| A5 | Real Stripe / Adyen never called | ENV | Provider seams are scripted in tests; needs sandbox-key runs. |

## B. Product decisions (OWNER)
| # | Item |
|---|------|
| B1 | DONE: recurring routes are real. Supported repeats: daily, weekdays, weekly, every 2 weeks, monthly, or chosen weekdays (`weekly:mon,wed,fri`). A nightly job (`routes:generate-recurring`) creates the next 7 days of runs as unassigned copies of the template (same stops, pay, custody and temperature rules, fresh tracking codes, delivery windows moved to the run date). Business route forms have a "Repeat" field. To stop a series, cancel the template route or clear its repeat. Open: runs start unassigned, so dispatch must assign each; auto-assigning a preferred driver is a product choice. |
| B2 | Delivery proof. Medical courier: required (receiver named, in the custody trail). Loads and Order Anywhere: the server records the receiver name whenever the driver app sends one and can REQUIRE it (`UG_REQUIRE_DELIVERY_PROOF=true`); it is OFF by default because the current driver app's complete call sends no receiver. Rollout: the driver-app prompt and API change are built and pushed (`askDeliveryRecipient()` + `completeJob(recipientName:)`), but NO driver-app screen calls `completeJob` yet (the active-jobs screen uses a different flow), so wire it where Order Anywhere and load deliveries are completed, test it on a device, and only then switch the flag on. Do not enable `UG_REQUIRE_DELIVERY_PROOF` before that. Signature/photo/PIN proof is a separate product decision. |
| B3 | Driver app flow: `/sequence` vs `/finish`; `routes/from-batch` is not called by the app. Left as is on purpose: the two differ in meaning (`finish` skips the dispatcher-approval workflow that `sequence` enforces above 20% / 15 miles variance), so switching the app changes how dispatchers keep control. Product decision; the server supports both. |
| B4 | DONE: customer-app entry points to Medical Courier, Load Board and Logistics are removed (deep links and concierge suggestions land on the hub). Building these in the driver app is a separate product item. |
| B5 | DONE: ride-share modules are ignored by the customer app. Building a ride-share backend is a separate product item. |
| B6 | Reviewed, left in place on purpose: the 11 routes in `known-broken-routes.txt` point at controller methods that do not exist, so each already returns a 500 and always has. No page or app calls any of them (the business manifest `validate`/`duplicate-check` routes are not referenced by any view and mis-target `BusinessAIController`; the subscription and payment-method API routes are dead constants in the apps). Removing them only turns a 500 into a 404 and risks breaking a named-route reference, so it is a cleanup item, not a launch item. A ratchet test already stops new ones appearing. |
| B7 | DONE: admin driver suspend and approve/deny, and the vendor-panel driver status and earning toggles, are POST with CSRF. |

## C. Certification lanes not finished (TODO)
| # | Lane | Status |
|---|------|--------|
| C1 | AI actions + Concierge | DONE with notes: see `AI_CONCIERGE_CERTIFICATION.md` (shared cache needed in prod; session-id entropy and guest chat retention now fixed) |
| C2 | Digital humans / avatar | DONE with notes: text cap fixed; guest `speak` has no global spend cap; persona register + real TTS not audited |
| C3 | Notifications | DONE with notes: see `NOTIFICATIONS_CERTIFICATION.md` (no user opt-out/consent, no SMS provider, MAIL_FROM + queue worker needed in prod) |
| C4 | Remaining customer/guest endpoints: wallet, track_order, refunds | DONE with notes: see `CUSTOMER_WALLET_REFUND_CERTIFICATION.md` (partner transfer id, refund setting null-deref, admin refund approval flow not re-audited) |
| C5 | Dispatch commission + financial reconciliation | DONE with notes: see `DISPATCH_FINANCIAL_RECONCILIATION_CERTIFICATION.md` (dispatcher commission on OA still open; split-row audit command and active-jobs `source` selector now added) |
| C6 | Creator/social + fashion ownership pass | DONE with notes: see `CREATOR_FASHION_CERTIFICATION.md` (vendor can mark `completed` from `pending`; reel moderation not re-audited) |
| C10 | Order Anywhere | DONE with notes: see `ORDER_ANYWHERE_CERTIFICATION.md` |
| C7 | Vendor update endpoints: payload-complete tests | DONE: see `VENDOR_UPDATE_ENDPOINTS_CERTIFICATION.md` (all tenant-scoped; malformed-body 500s fixed; image-upload variants not exercised) |
| C8 | Stranded: resume an unfinished request from the customer app | DONE: `loadResume()` + banner on the Stranded home screen (analyzer clean; not run on a device) |
| C9 | Final certification report | DONE: `FINAL_CERTIFICATION_REPORT.md` |

## F. Notifications follow-ups
| # | Item | Type |
|---|------|------|
| F1 | DONE (API + enforcement): per-customer preferences (`GET/PUT /api/v1/urban-goodz/notification-preferences`). Push and email default on; SMS and promotions are opt-in with timestamped consent; the in-app copy is always kept. Customer-app settings screen built (not run on a device). Vendor and driver preferences exist as API only (`/api/v1/urban-goodz/vendor/notification-preferences`, `/api/v1/urban-goodz/driver/notification-preferences`); their apps have no screen for them yet. | DONE (API) |
| F2 | SMS provider: wire one or drop the `sms` channel | OWNER |
| F3 | Production: set `MAIL_FROM_ADDRESS`, run a queue worker for `notifications` | ENV |

## E. Order Anywhere follow-ups
| # | Item | Type |
|---|------|------|
| E1 | DONE: pickup addresses are geocoded (admin quote form and again at dispatch) using the existing server map key (`map_api_key_server` business setting). Needs that key set in production. If an address cannot be located the request is marked `estimated_zone_center` so an admin can see "nearest" is only approximate. | ENV: set the key |
| E2 | Stripe Issuing float: who funds the issuing balance while customer captures settle | OWNER |
| E3 | Customer cancel after driver accepted: DONE as policy: full refund until the driver has bought the goods, then contact support. Confirm that is the policy you want. | OWNER |
| E4 | Set `ORDER_ANYWHERE_MARKUP_PERCENT`; confirm admins can see `admin_alert` notices in the panel. (Quote form with item cost, markup and pickup coordinates, and the dispatch settings card, are built and tested.) | OWNER/ENV |
| E5 | Tune defaults: radius 25 mi, offer window 3 min, escalate 10 min, auto-cancel 30 min | OWNER |
| E6 | DONE: guest speech has a platform-wide daily character budget (`UG_GUEST_SPEECH_DAILY_CHARS`, default 100,000; 0 turns it off). | DONE |

## G. Scheduler (found late, important)
| # | Item | Type |
|---|------|------|
| G1 | **The schedule in `app/Console/Kernel.php` was never read** (Laravel 12 uses `bootstrap/app.php`), so `stranded:dispatch-tick`, `order-anywhere:recover-card-issuance`, `delivery-history:prune` and `run-scheduled-sourcing` have never run in production. All are now on the live schedule, guarded by a test. `run-scheduled-sourcing` is opt-in (`LOAD_SOURCING_SCHEDULED_ENABLED=true`) because it has never run and works with external load sources. | DONE, confirm in prod that `schedule:run` is wired to cron |
| G2 | First-run hazards of the newly live jobs were reviewed and fixed: the history pruner deletes in capped batches (it has never run, so the first run could face millions of rows); the Stranded tick closes requests stuck for more than 6 hours quietly (no messages about old emergencies) and only penalises responders for offers that lapsed in the last 15 minutes. Tunable: `STRANDED_MAX_LIVE_HOURS`, `STRANDED_MISSED_OFFER_WINDOW_MINUTES`. Still worth watching the first night in production. | DONE / watch |

## D. Closed so far (for traceability)
Pickup geocoding, delivery-proof recording (enforcement flagged), notification preferences and consent, capture-time payout-split alert.
Recurring routes built. Scheduler consolidated into the live schedule (four jobs had never run).
Parallel lane (this stretch): guest speech budget, guest chat session ids + daily pruning, refund-setting crash, late-assigned driver pay settled once, `order-anywhere:audit-splits`, customer cancel with full refund until purchase, active-jobs `source` selector, medical proof-of-delivery, Stranded ledger honesty, driver/vendor state-change links to POST, customer app dead-screen entry points removed.
Vendor updates: payload-complete isolation proven for 7 endpoints; missing-translations 500s and coupon validation bypass fixed.
Dispatch/finance: route reopen double-payout, non-atomic completion, active-jobs completing routes/orders without checks, transitionTo not saving `completed`, driver pay missing from Order Anywhere splits.
Creator/fashion: stylist-request status IDOR, creator self-activating rejected/pending assignments.
Customer wallet/refund: partner credit hardening, refund double-tap + guest 500, missing refund_requested column, track throttle.
Notifications: push payload showed generic text, email never sent, SMS/webhook falsely pending.
Order Anywhere: customer-chosen charge, vendor/admin_notes injection, broken driver flow, in-progress dispatches expiring, manual approval, silent no-driver, admin cancel not voiding, refund rollback hazard, markup computation.
Concierge video-avatar ownership + billed-room resume.
Admin 419 mechanism, captcha bypass + remember-me cookie removed, driver auth/token, customer order/cart/chat ownership,
vendor tenancy, logistics tenancy, route optimization quality, OTR/medical races + custody, payout race + vendor payout
NOT NULL, Stranded fee double-charge / escrow release / double close.
