# Urban Goodz — Production Readiness Certification Report

**Release decision: NOT READY FOR PRODUCTION**

Branch `claude/focused-fermat-smwp94` (backend, customer app, vendor app). Prepared 2026-11-10.

This report states what was tested, what was fixed, what was *not* tested, and what must happen before launch. Nothing
below is claimed as verified unless an automated test, a measurement or a direct reading of the code supports it.
Where something could not be verified it says so.

---

## 1. Decision and why

**NOT READY.** The platform is materially safer and more correct than when this effort began: a large number of
authorization, money and concurrency defects were found, reproduced, fixed and covered by regression tests (section 4). But
three kinds of gap remain that I cannot close from a cloud container, and a certification cannot responsibly paper over them:

1. **Nothing was run on a real device, in a real browser, or against a real payment provider.** Every app-level behaviour,
   every push, every card and every Stripe/Adyen call was verified only through feature tests, unit tests and scripted
   provider seams.
2. **The P0 admin 419 is fixed and unit-verified, but not verified in the environment that produced it.** It depends on
   production settings (section 6).
3. **Several features are not finished or need an owner decision that affects money** (section 5), most importantly Stranded
   helper rewards, which today are bookkeeping only (no money moves).

**Path to CONDITIONALLY READY:** complete the production setup in section 6, confirm the 419 fix in the real environment,
run the device and sandbox-payment checks in section 7, and make the four money decisions at the top of section 5. None of
these require new engineering of the existing flows; they require access and decisions.

---

## 2. Evidence base

| Evidence | Result |
|---|---|
| Backend automated suite (`vendor/bin/phpunit`, MariaDB test DB, run in isolation) | **1,487 tests, 27,869 assertions, 0 failures, 0 errors, 6 skipped** (the skips are existing opt-in tests) |
| Flutter customer app (`flutter analyze` / `test`) | analyzer clean on changed files; 388 tests passed at the customer-app pass |
| Flutter driver app | analyzer clean; 192 tests passed |
| Flutter vendor app | 160 tests passed (153 existing + 7 added) |
| Real concurrency | 8-process races for load/specimen assignment, delivery payout, driver and vendor payout requests (each proven to fail without the fix) |
| Measured quality | Route sequencing measured against brute-force optima before and after |

**Not available / not done:** physical device or emulator, Playwright/Appium runs against a live host, real Stripe/Adyen/Tavus/
ElevenLabs/Firebase calls, a staging database with production-shaped data, production configuration.

Test isolation note: the database service in this environment stops periodically; failures that were "connection refused"
were re-run after restart and are not counted as results.

---

## 3. Lane-by-lane status

| Lane | Status | Headline |
|---|---|---|
| Admin portal | PARTIAL | HTTP-level render/route sweep only; no browser pass. 419 mechanism found and fixed; captcha bypass and remember-me password cookie removed |
| Customer app + API | PARTIAL | 5 critical authorization flaws fixed (wallet, order read, guest/customer id collision, cart takeover, private chats). No device run |
| Driver app + API | PARTIAL | Suspended/denied drivers kept access; legacy endpoints ran as a logged-out driver. Fixed. No device run |
| Vendor app + API | PARTIAL → API tenant isolation **proven** | Cross-vendor delete/edit/read holes fixed; payload-complete tests now prove all 7 update endpoints are tenant-scoped |
| Logistics / dispatch / route optimization | PARTIAL | Cross-company driver and dispatch leaks fixed; sequencer was 25–38% longer than optimal, now 0–0.4% mean gap |
| OTR (load board) + medical courier | PARTIAL | API open to any customer account fixed; 8-way races fixed; custody trail made honest. Recurring routes now built (runs start unassigned) |
| Order Anywhere | PARTIAL | Customer-chosen charge, broken driver flow, silent dispatch stalls fixed; automatic nearest-first dispatch built. Real provider money not exercised |
| Stranded | PARTIAL | Fee double-charge and "helper never paid" fixed; resume flow added. **Reward payouts are bookkeeping only** |
| AI actions + Concierge | DONE with notes | Guest concierge is text-only (cannot act); video-avatar ownership and billed-room reuse fixed |
| Digital humans | DONE with notes | Unbounded text on open endpoints capped; no global spend cap on guest speech |
| Notifications | DONE with notes | Push showed generic text, email never sent, SMS falsely "pending": all fixed. Customers now have preferences and consent (SMS and promotions are opt-in) |
| Customer wallet / tracking / refunds | DONE with notes | Partner credit endpoint hardened; refund double-tap and guest 500 fixed |
| Creator / social + fashion | DONE with notes | Stylist-request status IDOR and creator self-activation fixed |
| Dispatch commission + financial reconciliation | DONE with notes | Double-payout by reopening a route and driver pay missing from Order Anywhere splits fixed |
| Payouts / withdrawals | DONE | Over-withdrawal race and "vendor payouts can never be created" fixed |
| Security (cross-cutting) | See §4 | IDOR/tenancy was the dominant defect class |
| Mobile (device) | **NOT TESTED** | No device reachable |

Status words: DONE = reviewed, defects fixed, regression-tested, residual items listed. PARTIAL = reviewed and fixed at the
API/service level; the user-facing client was not run.

---

## 4. Defects found and fixed

Every item below was reproduced (or its mechanism proven from code) before the fix, and has a regression test. Detail and
file references are in the per-lane documents listed in section 9.

### Critical: authorization and tenancy (P0/P1)
- **Customer wallet debit by anyone**: `wallet-payment` accepted any order id from any caller, including unauthenticated, and debited the owner's wallet.
- **Order read by incrementing id** (guests had no ownership filter); **guest id = customer id collision** let a guest read, cancel and re-pay a customer's orders.
- **Cart takeover and private chats**: a customer could modify and take over another's cart lines and read/write any conversation.
- **Vendor B could delete/edit vendor A's items, banners, add-ons, payout methods, reviews, coupons, and any driver on the platform.**
- **Medical courier and load-board APIs were open to any logged-in customer**, who could accept as any driver and move jobs to `delivered` (crediting wallets).
- **Dispatcher portal and business dispatch leaked drivers and dispatches across companies**; any approved driver could build a run from any company's intake batch.
- **Suspended or denied drivers kept full API access**; 37 legacy driver endpoints ran as a logged-out driver for Bearer callers.
- **Stylist-request status and creator assignment states** could be set by the wrong party.
- Admin: captcha bypass for one hard-coded email and a password stored in a "remember me" cookie removed.

### Critical: money
- **Customer chose the amount authorized** for Order Anywhere ($0.01 against a $60 quote). Now always the quote.
- **Stranded fee**: a declined card followed by a good card charged Stripe and then crashed on a unique ledger key (charged but unpaid); concurrent submissions could double-charge. Fixed with per-attempt idempotency keys and an atomic claim.
- **Stranded helpers were never paid** (customer app closes with `completed`, which never released escrow); double-tap counted a job twice.
- **Payout/withdrawal race**: concurrent requests could withdraw more than owed (8 of 8 succeeded); vendor payouts could never be created (NOT NULL column).
- **Route reopened and completed again produced a second payout and bonus**; route completion was not atomic; the active-jobs endpoint completed routes without checking packages or settling.
- **Order Anywhere driver pay was never allocated** in the automatic flow, while reconciliation still passed (it checks summary columns).
- **Refund after capture threw after the provider had already refunded**, rolling back the database (double-refund hazard); admin cancel never voided the customer's card hold.
- **Partner wallet credit** (no user session): loose secret comparison, unvalidated amount (negative debited), non-atomic credit, replayable.
- **Refund request**: two taps created two refunds; guest request returned a 500.
- **Race conditions in assignment and payment**: with 8 drivers on one load/job all 8 won and each paid the driver.

### Correctness and honesty
- A captured Order Anywhere request never reached `completed` in the database (transition did not save).
- In-progress deliveries were marked `expired` whenever any driver opened their dispatch list.
- Driver bidding answered "Bid submitted" while saving nothing; hazmat loads could be taken without an endorsement.
- Chain-of-custody seals were always recorded intact whatever happened.
- Push notifications arrived as "Urban Goodz" with no text; email and SMS rows were reported as created but never sent.
- Route sequencing was 25–38% longer than optimal (three defects in the optimizer).
- Several Blade pages and redirects 500ed (syntax error, nonexistent route names) now guarded by compile and route-resolution tests.
- Malformed vendor request bodies returned 500 instead of a validation error; coupon validation could be bypassed.

---

## 5. Open items that block or condition release

### Money decisions (OWNER): needed before launch
1. **Stranded helper rewards are paid directly by the customer, not by the platform.** The ledger and API now say so (no provider, nothing collected) instead of claiming a completed Stripe payment. If you want the platform to hold and pay out rewards, that is Stripe Connect work and a new decision.
2. **Stripe Issuing float** for Order Anywhere purchase cards: cards spend from the platform's issuing balance while customer captures settle. Someone must fund it.
3. **Customer cancel policy** is now: full refund until the driver has bought the goods, then support. Confirm that is the policy you want.
4. **Order Anywhere defaults** to confirm or tune: driver radius 25 mi, offer window 3 min, alert admins at 10 min, auto-cancel and release the hold at 30 min, markup percent (no default by design).

### Product decisions (OWNER)
- **Recurring routes are now real** (daily, weekdays, weekly, every 2 weeks, monthly, chosen weekdays): a nightly job creates the next week of runs as unassigned copies of the template. Runs still need a driver assigned by dispatch; auto-assigning a preferred driver is a product choice.
- **Proof of delivery** is required for medical courier jobs. For loads and Order Anywhere the server records the receiver when sent and can require it (`UG_REQUIRE_DELIVERY_PROOF`), off until the driver app's new prompt is wired into a screen and tested (the dialog is built, but nothing calls it yet). Signature, photo or PIN proof is still a product decision.
- **Customer-app Medical Courier and Logistics screens now return 401** (they used customer tokens against driver tasks); the driver app has no UI for them.
- Ride-share modules are now ignored by the customer app (no backend exists). Driver app flow `/sequence` vs `/finish`, and `from-batch` is not called by the app.
- Admin and vendor-panel driver suspend/approve are now POST with CSRF. Orphan legacy routes remain.
- **Notifications:** customers now have preferences (push and email on by default; SMS and promotions opt-in with timestamped consent). No SMS provider is integrated, and vendors and drivers have no preferences yet.

### Engineering follow-ups (known, scoped)
- Reconciliation should sum the actual split rows, not just request columns.
- Pickup addresses are now geocoded with the existing server map key (set `map_api_key_server`); an address that cannot be located is marked as an estimate so an admin can see it.
- Active-jobs resolves a bare job id across four tables in a fixed order (id collisions possible).
- Dispatcher commission on Order Anywhere is not attached by the automatic flow.
- Guest concierge session ids are client-chosen (a guessable id exposes that chat); guest chats are never pruned; no global spend cap on guest speech.
- Partner wallet transfers carry no unique transfer id (replay protection is a 120-second window).
- Test-suite hygiene: another test in the suite alters mass-assignment behaviour; the tests I added no longer depend on it, but the polluter was not identified.

---

## 6. Production deployment checklist (required)

**Migrations to run** (those authored in this effort; run the full pending set):
- `2026_11_07_000001_make_payout_request_delivery_man_nullable` — vendor payouts fail without it.
- `2026_11_08_000001_add_refund_requested_to_orders_if_missing` — refund requests fail on migration-built databases without it.
- `2026_11_10_000001_add_recurring_parent_to_dedicated_routes` — needed for recurring routes.
- `2026_11_10_000002_create_urban_goodz_notification_preferences` — needed for notification preferences.

**Environment**
- `TRUSTED_PROXIES` (load balancer CIDRs or `*`), `APP_URL=https://<admin host>`, `SESSION_SECURE_COOKIE=true`; `SESSION_DRIVER=redis|database` if more than one instance serves the admin. Run `php artisan config:clear` after. (This is the 419 fix; see `ADMIN_419_INCIDENT.md`.)
- `MAIL_FROM_ADDRESS` — without it every notification email fails.
- `ORDER_ANYWHERE_MARKUP_PERCENT` (no default by design), and optionally `ORDER_ANYWHERE_AUTO_DISPATCH`, `_DISPATCH_RADIUS_MILES`, `_OFFER_MINUTES`, `_ESCALATE_MINUTES`, `_CANCEL_MINUTES`, `_CAPTURE_ON_ACCEPT`. These can also be changed by an admin on the Order Anywhere page.
- A **shared cache store** (Redis), not `file`, on multi-server setups: used for video-avatar ownership and the partner-transfer replay guard.

**Processes**
- **Scheduler (critical).** The schedule Laravel 12 actually reads is `bootstrap/app.php`. The old `app/Console/Kernel.php` schedule was never read, so Stranded escalation, card-issuance recovery and driver-history pruning had never run in production. They are now on the live schedule, guarded by a test, along with `order-anywhere:dispatch-pending` (automatic dispatch cycling), `routes:generate-recurring` (nightly) and `ai-concierge:prune-guests` (daily). `run-scheduled-sourcing` is opt-in (`LOAD_SOURCING_SCHEDULED_ENABLED=true`). Cron must call `schedule:run` every minute, and the first run of the newly live jobs should be watched.
- Queued jobs (including the `notifications` queue) are processed by the scheduled `queue:work`, so the scheduler is the only process you need. If you run a dedicated worker instead, give it `payments,notifications,ai,load-sourcing,default`.

**Provider setup:** Stripe keys and webhook secrets (payments, Issuing), Adyen if used, Firebase credentials, distance-matrix provider key (otherwise routes are optimized for straight lines, not roads), Tavus and voice provider keys if those features are enabled.

---

## 7. What must still be verified, and by whom

| Verification | Needs | Notes |
|---|---|---|
| 419 fix in the real environment | Production/staging access | Reproduce the original failure behind the real proxy, apply settings, confirm login holds |
| Device runs: customer, driver, vendor apps | A phone on the owner's computer, reachable from a session on that computer | Remote Control is on and the computer is visible, but the folders were reported "not available" to cloud sessions; update the Claude desktop app on that computer and re-check, or run a session there. Then `adb` + Flutter device tests, background location, push, camera/POD, offline |
| Browser pass: admin, vendor, business portals | Playwright against staging | CSRF in a real browser, forms, AJAX |
| Sandbox payments end to end | Stripe/Adyen sandbox keys | Authorize → capture on accept → refund/void; Issuing card spend; webhooks |
| Push to real devices | Firebase + device | The transport was mocked |
| Staging data pass | Staging DB | The admin page sweep had 47 failing pages in an empty test DB, a mix of real defects (listed) and schema artifacts that cannot be judged here |

---

## 8. Honest limits of this certification

- "Tenant isolation proven" means proven at the HTTP API with complete payloads for the endpoints enumerated, not that every endpoint of the platform has been probed. Endpoints I did not enumerate are not claimed.
- The admin portal certification is HTTP-level only, as the primary administrator. Non-primary roles and employee permissions were not exercised.
- Image-upload variants of several update endpoints were not exercised.
- Dispatch and route quality were measured with straight-line distances.
- A passing automated suite shows the tested behaviour is correct; it does not show the absence of other defects. The defect rate found in this effort (including several P0 authorization flaws on live endpoints) is itself a reason to expect more in code that has not been reviewed.
- If an earlier build of the customer app/API is already deployed, the customer authorization flaws in section 4 should be treated as a potential incident: review wallet transactions and order status changes made by unauthenticated or guest callers.

---

## 9. Document index (`docs/qa/`)

`PUNCH_LIST.md` (live list of everything unresolved) · `ADMIN_419_INCIDENT.md` · `ADMIN_PORTAL_CERTIFICATION.md` ·
`CUSTOMER_APP_CERTIFICATION.md` · `CUSTOMER_WALLET_REFUND_CERTIFICATION.md` · `DRIVER_APP_CERTIFICATION.md` ·
`VENDOR_APP_CERTIFICATION.md` · `VENDOR_UPDATE_ENDPOINTS_CERTIFICATION.md` · `LOGISTICS_DISPATCH_CERTIFICATION.md` ·
`OTR_MEDICAL_COURIER_CERTIFICATION.md` · `ORDER_ANYWHERE_CERTIFICATION.md` · `STRANDED_CERTIFICATION.md` ·
`AI_CONCIERGE_CERTIFICATION.md` · `NOTIFICATIONS_CERTIFICATION.md` · `CREATOR_FASHION_CERTIFICATION.md` ·
`DISPATCH_FINANCIAL_RECONCILIATION_CERTIFICATION.md` · `PAYOUT_RACE_CERTIFICATION.md`.

Regression tests added in this effort include: `AdminSessionCsrfRegressionTest`, `BladeViewsCompileTest`,
`NamedRouteReferencesResolveTest`, `CustomerOrderOwnershipTest`, `CustomerCartAndChatOwnershipTest`, `DriverAuthorizationTest`,
`VendorTenantIsolationTest`, `VendorUpdatePayloadCompleteTest`, `LogisticsTenantIsolationTest`, `LogisticsPortalPagesRenderTest`,
`RouteOptimizationQualityTest`, `RouteGeographicGroupingTest`, `DriverBatchToSortedRunTest`, `OtrAndMedicalCourierTest`,
`ConcurrentAssignmentTest`, `PayoutRaceTest`, `MedicalRouteCustodyTest`, `StrandedMoneyFlowTest`,
`ConciergeVideoAvatarOwnershipTest`, `OrderAnywhereJourneyTest`, `OrderAnywhereAutoDispatchTest`,
`OrderAnywhereMoneyReconciliationTest`, `OrderAnywhereAdminWiringTest`, `DriverJobLifecycleMoneyTest`,
`NotificationPayloadAndOptOutTest`, `PartnerWalletTransferTest`, `CustomerRefundRequestTest`, `StylistRequestOwnershipTest`,
`CreatorAssignmentTransitionTest`.

To reproduce the headline result: `vendor/bin/phpunit` against the allow-listed test database (see `tests/CreatesApplication.php`).
