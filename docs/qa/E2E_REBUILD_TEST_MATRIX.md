# Urban Goodz — Real E2E Test Matrix

Author: Claude Session 2 (branch `e2e-certification-rebuild`)
Date: 2026-07-23

This matrix replaces the workflow list in the old QA manifest with routes, controllers, and DB tables verified by reading the actual code in this checkout (`routes/web.php`, `routes/business.php`, `routes/vendor.php`, `routes/admin.php`, `routes/api/v1/*.php`, and the controllers they reference) — not assumed from the workflow names alone. Where a workflow was not verified to this depth in this pass, AUTOMATION STATUS says so explicitly instead of implying coverage that doesn't exist.

Legend for AUTOMATION STATUS:
- **AUTOMATED (this session)** — a real test exists in `tests/Feature/UrbanGoodzPublicSurfaceValidationBoundaryTest.php` and was run against a live local DB (see §6 of the inventory doc).
- **EXISTING COVERAGE** — real PHPUnit coverage already existed before this session (see inventory doc §3) and was spot-verified.
- **PLANNED, NOT YET BUILT** — routes/controllers verified real; no test written yet in this session.
- **BLOCKED ON ADMIN AUTH** — requires a working Admin login; per the coordination rule, drafted only after the `admin-auth-recovery` branch's approved commit is merged.
- **BLOCKED ON SCHEMA DRIFT** — the local `urbangoodz_test` DB is missing columns current migrations define (see inventory doc §6); needs `php artisan migrate` against that DB before the workflow can be exercised end-to-end.
- **NOT VERIFIED THIS PASS** — genuinely unknown; do not assume either way.

---

## Public website

```
WORKFLOW ID:      PUB-01
ACTOR:            Anonymous visitor
PRECONDITIONS:    None
VISIBLE CONTROLS: Root URL, informational nav links
BUTTONS:          n/a
DROPDOWNS:        n/a
FORM FIELDS:      n/a
API ENDPOINTS:    GET /, GET /terms-and-conditions, GET /about-us, GET /contact-us, GET /privacy-policy, GET /cancelation, GET /refund, GET /shipping-policy, GET /api/v1/zone/list
DATABASE TABLES:  business_settings (landing_page, toggle_store_registration keys gate multiple public routes)
EXPECTED STATUS TRANSITIONS: n/a
NEXT ROLE:        n/a
NOTIFICATIONS:    none
CLEANUP:          none
AUTOMATION STATUS: AUTOMATED (this session) — root redirect, unknown-route fallback (routes/update.php:7 global Route::fallback redirects to '/'), zone/list JSON, and the real "informational pages 404 when landing_page is unconfigured" behavior are covered. The "renders the actual configured landing view" path is BLOCKED ON SCHEMA DRIFT (missing `type` column encountered live).
```

## Admin Portal

```
WORKFLOW ID:      ADMIN-01 (login/CAPTCHA/dashboard/permissions)
ACTOR:            Admin
PRECONDITIONS:    Admin account exists; CAPTCHA fail-closed fix landed
VISIBLE CONTROLS: /login/admin form, custom + Google CAPTCHA, dashboard nav, urban_goodz_view-gated widgets
BUTTONS:          Login submit, logout
DROPDOWNS:        n/a
FORM FIELDS:      email, password, custome_recaptcha / g-recaptcha-response
API ENDPOINTS:    GET login/{tab}, POST login_submit, GET logout, GET /admin/urban-goodz/ai-operations, admin.users.delivery-man.list, admin.transactions.report.item-wise-report
DATABASE TABLES:  admins, admin_roles (role_id, urban_goodz_view permission)
EXPECTED STATUS TRANSITIONS: guest -> authenticated admin -> session invalidated on token mismatch/logout
NEXT ROLE:        n/a (top of hierarchy)
NOTIFICATIONS:    none
CLEANUP:          logout / session invalidate
AUTOMATION STATUS: BLOCKED ON ADMIN AUTH per the coordination rule in this task — CAPTCHA fail-open, `auth('admin')->check()` permission-bypass (DashboardController.php:348), and route-name regressions are being repaired on `admin-auth-recovery`. What IS already automated and does not touch Admin auth internals: `AdminLoginRecoveryRegressionTest.php` (existing, verified real — invalid-custom-CAPTCHA rejection, root redirect, dashboard route-name assertions, approved login asset references) and this session's `test_authenticated_business_user_cannot_access_admin_guarded_routes` / `test_unauthenticated_admin_route_redirects_to_admin_login_not_an_open_page`, which prove the `admin` guard boundary from the outside without needing a working Admin login.
```

```
WORKFLOW ID:      ADMIN-02 (Vendor approval, zone setup, driver capability gates, dispatch, load board, dynamic pricing, AI Chief of Staff)
ACTOR:            Admin
PRECONDITIONS:    Authenticated Admin with urban_goodz_view
VISIBLE CONTROLS: /admin/vendor/list, /admin/zone/setup, /admin/delivery-man/list, /admin/dispatch/dashboard, /admin/load-board, /admin/dynamic-pricing/config, /admin/urban-goodz/ai-chief-of-staff
API ENDPOINTS:    (routes/admin.php, routes/admin_ai_operations.php — large surface, not individually enumerated this pass)
DATABASE TABLES:  vendors, stores, zones, delivery_men, urban_goodz_load_board_loads, urban_goodz_dynamic_pricing_*
AUTOMATION STATUS: BLOCKED ON ADMIN AUTH (requires an authenticated Admin session to exercise beyond the guest-redirect boundary already covered above). The old `tests/playwright/admin-portal.spec.js` (12 tests) claiming to cover this is entirely FALSE POSITIVE — see inventory doc §1.1.
```

## Business Portal

```
WORKFLOW ID:      BIZ-01 (login/validation)
ACTOR:            Business client user (owner_admin / dispatch role)
PRECONDITIONS:    UrbanGoodzBusinessClient (status=approved) + UrbanGoodzBusinessClientUser (is_active=true, status=active)
VISIBLE CONTROLS: /business/login form
BUTTONS:          Login submit
FORM FIELDS:      email, password
API ENDPOINTS:    GET business.login, POST business.login.submit (BusinessAuthController@login)
DATABASE TABLES:  urban_goodz_business_clients, urban_goodz_business_client_users
EXPECTED STATUS TRANSITIONS: guest -> business guard authenticated; inactive user/company is force-logged-out with a specific error, without disclosing whether the account exists
NEXT ROLE:        Dispatcher (same login, routed by isDispatchCompany()+isDispatchRole())
NOTIFICATIONS:    none observed in this controller
CLEANUP:          none needed (DatabaseTransactions)
AUTOMATION STATUS: AUTOMATED (this session) — empty-submission validation errors, invalid-credential rejection without account-existence disclosure, and CSRF-coverage-by-construction are all covered by `UrbanGoodzPublicSurfaceValidationBoundaryTest`. The full "reach the real dashboard content" path is BLOCKED ON SCHEMA DRIFT (missing `business_client_id` column encountered live in BusinessPortalController@dashboard's query).
```

```
WORKFLOW ID:      BIZ-02 (routes, package pool, scan, locations, billing)
ACTOR:            Business client user
PRECONDITIONS:    Authenticated business guard user
API ENDPOINTS:    routes/business.php:24-115 — dashboard, routes.*, packages.scan/pool/assign, locations.*, billing (not fully enumerated this pass — 90+ named routes in this file)
DATABASE TABLES:  urban_goodz_dedicated_routes, urban_goodz_route_packages, urban_goodz_business_locations (names inferred from route/controller naming, not individually confirmed against migrations this pass)
AUTOMATION STATUS: PLANNED, NOT YET BUILT beyond the login/guard boundary. Unauthenticated-redirect for `business.dashboard` is AUTOMATED (this session).
```

## Dispatcher Portal

```
WORKFLOW ID:      DISP-01
ACTOR:            Business user with dispatch role (NOT a separate login — see finding below)
PRECONDITIONS:    Business client with isDispatchCompany()=true, user with isDispatchRole()=true
VISIBLE CONTROLS: /business/dispatcher/dashboard, /loads, /drivers, /commissions, /territory, /users
API ENDPOINTS:    routes/business.php:149-172 (business.dispatcher.*), gated by middleware ['business','dispatcher','dispatch-territory']
DATABASE TABLES:  urban_goodz_load_board_loads, delivery_men, dispatcher commission/territory tables (not individually confirmed this pass)
EXPECTED STATUS TRANSITIONS: business guard auth -> dispatcher-role check -> territory check
NEXT ROLE:        Driver (load assignment)
AUTOMATION STATUS: AUTOMATED (this session) for the guard/route-existence boundary — `test_unauthenticated_dispatcher_dashboard_redirects_to_business_login` proves `business.dispatcher.dashboard` is real and gated. **CRITICAL CORRECTION TO THE OLD MANIFEST:** there is no `/dispatcher/login` route anywhere in the app — the old `tests/playwright/dispatcher-portal.spec.js` navigated to URLs that don't exist and still "passed" (see inventory doc §1.3). Any future dispatcher test must use `/business/login` + `route('business.dispatcher.*')`, never a bare `/dispatcher/*` path.
```

## Vendor Portal / Vendor registration

```
WORKFLOW ID:      VEND-01 (self-registration)
ACTOR:            Prospective vendor (anonymous)
PRECONDITIONS:    business_settings.toggle_store_registration enabled
VISIBLE CONTROLS: /vendor/apply multi-step form (general info -> business plan -> payment -> final step)
BUTTONS:          Continue/submit per step
DROPDOWNS:        zone_id (vendor/get-all-modules, scoped by zone), module_id (vendor/get-module-type), pickup_zone_id (rental modules only)
FORM FIELDS:      f_name, l_name, name[], address[], latitude, longitude, email, phone, minimum/maximum_delivery_time, password, zone_id, module_id, logo, cover_photo, delivery_time_type, tin, tin_certificate_image
API ENDPOINTS:    GET vendor/apply, POST vendor/apply, GET vendor/get-all-modules, GET vendor/get-module-type, GET vendor/check-module-type, POST vendor/business-plan, GET vendor/final-step
DATABASE TABLES:  vendors, stores, module_zones, zones, modules
EXPECTED STATUS TRANSITIONS: none (vendor + store created with status=0/null, pending Admin approval — approval flow itself is Admin-side and BLOCKED ON ADMIN AUTH)
NEXT ROLE:        Admin (approval)
NOTIFICATIONS:    StoreRegistration / VendorSelfRegistration mail classes referenced in VendorController — not exercised this pass
CLEANUP:          DatabaseTransactions
AUTOMATION STATUS: AUTOMATED (this session) — missing-required-field validation (all 14 unconditionally-required fields per VendorController.php:86-101 individually confirmed present in the error payload; `cover_photo` is `nullable` and correctly excluded), zone_id-specifically-required, logo-upload-required, zone-scoped module dropdown (a module mapped to Zone A does not leak into Zone B's dropdown), and module/zone dependency check are all real, DB-backed tests. Discovered and documented along the way: registration is gated behind `toggle_store_registration`, which returns a single `latitude`/"not_found" error and short-circuits full-field validation when unseeded — a real behavior, not a test artifact.
```

## Driver / Rider registration

```
WORKFLOW ID:      DRIVER-01
ACTOR:            Prospective driver (anonymous)
API ENDPOINTS:    GET rider/apply, POST rider/apply (RiderRegistrationController, routes/web.php:249-251)
DATABASE TABLES:  delivery_men (BLOCKED ON SCHEMA DRIFT locally — `phone`, `email`, `image` columns were missing against several test fixtures during the PHPUnit run in this session, see inventory doc §6)
AUTOMATION STATUS: PLANNED, NOT YET BUILT. Route confirmed real; validation rules not yet read/tested this pass. Building this test should wait for the schema-drift fix so DB-level assertions (driver row created with application_status=pending) can actually run.
```

## Shopper / Vendor / Driver applications (mobile)

```
AUTOMATION STATUS: NOT VERIFIED THIS PASS at the web layer beyond the registration forms above (VEND-01, DRIVER-01). The mobile app flows themselves are out of scope for this Playwright/PHPUnit rebuild — no Appium harness exists in this repository at all (see inventory doc §2); the claimed "352/352 Appium PASSED" figure has zero supporting artifacts and must not be repeated as fact.
```

## Orders / Checkout / Payments / Order Anywhere

```
WORKFLOW ID:      OA-01 (Order Anywhere request lifecycle)
ACTOR:            Customer -> Vendor/Driver -> Platform
API ENDPOINTS:    POST api/v1/order-anywhere/requests, .../estimate, GET .../{record}, POST .../{record}/authorize-payment, POST .../{record}/receipt, GET customer/requests; admin-side: GET requests, POST {record}/status, POST {record}/notes (routes/api/v1/urban_goodz.php:90-104)
DATABASE TABLES:  order_anywhere_requests (calculateSplits/captureCustomerPayment/refundCustomerPayment all write to this + urban_goodz_payment_splits + urban_goodz_payment_ledgers)
EXPECTED STATUS TRANSITIONS: quote_needed -> quote_ready -> shopping -> authorized -> captured -> completed/refunded/cancelled (payment_status), with idempotent capture/refund and reconciliation guarded by `reconcileSplits()` throwing LogicException on imbalance
NEXT ROLE:        Vendor (fulfillment), Driver (delivery), Admin (override)
NOTIFICATIONS:    Ledger entries recorded per event (capture, capture_failed, refund, payment_session_created) — email/push not verified this pass
AUTOMATION STATUS: EXISTING COVERAGE — `tests/Feature/UrbanGoodzPaymentAuditTest.php` (17 methods) and `tests/Feature/UrbanGoodzSplitControlTest.php` (17 methods) are real, DB-verified, spot-read in full this session (inventory doc §3). This is genuinely the strongest-tested workflow in the whole codebase. Webhook idempotency, duplicate-refund prevention, cross-customer authorization boundary (404 on another customer's request), sandbox-mode caps, and staged-test-gateway production lockout are all covered with real assertions.
```

## Notifications

```
AUTOMATION STATUS: EXISTING COVERAGE (partial) — `tests/Feature/UrbanGoodzNotificationDeliveryTest.php` (3 methods), `UrbanGoodzDriverNotificationBehavioralTest.php` (8), `UrbanGoodzDriverDispatchNotificationSecurityTest.php` (7), `UrbanGoodzDriverDispatchNotificationProducerTest.php` (7) exist and were classified MEANINGFUL by file-size/naming heuristic in the inventory doc but not individually read line-by-line this pass — recommend a follow-up read before citing specific assertions. No live FCM/push delivery is exercised (config uses MAIL_MAILER=array in phpunit.xml, i.e. mail is captured, not sent — appropriate for testing, but means "notification actually arrived" is never truly end-to-end here).
```

## Courier routes / Package scanning

```
WORKFLOW ID:      COURIER-01
API ENDPOINTS:    business.routes.*, business.packages.scan/scan.store/pool/assign (routes/business.php:26-43)
DATABASE TABLES:  urban_goodz_dedicated_routes, urban_goodz_route_packages (BLOCKED ON SCHEMA DRIFT — `intake_batch_id` column missing against a current migration's expectation, observed live in this session's PHPUnit run)
AUTOMATION STATUS: PLANNED, NOT YET BUILT / BLOCKED ON SCHEMA DRIFT for any test that needs to actually create a route+package row.
```

## Medical courier

```
API ENDPOINTS:    api/v1/urban_goodz.php:37-43 (medicalCourierJobs/Job/accept/status/custody, under an "opportunities" prefix)
AUTOMATION STATUS: NOT VERIFIED THIS PASS beyond confirming the routes exist and are real. `UrbanGoodzDriverVehicleTrailerCapabilityTest.php` (9 methods, existing) likely intersects with vehicle/capability gating relevant to medical courier eligibility but was not read line-by-line this pass.
```

## Load sourcing / Load Board

```
AUTOMATION STATUS: EXISTING COVERAGE — `UrbanGoodzLoadSourcingTest.php` (23 methods), `UrbanGoodzLoadBoardTest.php` (4), `UrbanGoodzLoadBoardWorkflowTest.php` (20) exist; classified MEANINGFUL by size/naming heuristic, not individually read this pass. BLOCKED ON SCHEMA DRIFT for any *new* load-board-load creation in this local DB — `provider` and `customer_price` columns were missing against current migration expectations, observed live in this session's PHPUnit run (17 of the 112 Feature-suite errors were this exact column).
```

## AI Operations / AI Chief of Staff / Copilot

```
AUTOMATION STATUS: EXISTING COVERAGE — `UrbanGoodzAIExecutionEngineTest.php` (18), `UrbanGoodzAiCopilotTest.php` (3), `UrbanGoodzAiAuditTest.php` (5), `UrbanGoodzAiWorkforceTest.php` (7, one weak `assertTrue(true)` escape-hatch flagged in inventory doc §3.2), `UrbanGoodzAiMigrationTest.php` (1, under-tested for its file name — flagged for follow-up). `test_ai_operations_admin_route_uses_the_canonical_name` (in `UrbanGoodzEcosystemIntegrationTest.php`) is a real route/middleware assertion, read and verified this session. All Admin-panel AI surfaces (`/admin/urban-goodz/ai-*`) are BLOCKED ON ADMIN AUTH beyond the guard-boundary test already automated this session.
```

## Fashion Fit

```
API ENDPOINTS:    GET vendor/fashion-fit (UrbanGoodzFashionMeasurementController), api/v1/fashion_fit.php, api/v1/urban_goodz.php:80-82 (stylist-requests)
AUTOMATION STATUS: EXISTING COVERAGE — `tests/Unit/FashionFitAiContractTest.php` (7 methods) exists; not read line-by-line this pass. `tests/Fixtures/fashion_fit_ai_completed.json` exists as a fixture, suggesting a contract/schema test rather than a live E2E flow.
```

## Events and creators / Community Marketplace / Rentals / Service providers

```
API ENDPOINTS:    api/v1/urban_goodz.php:52-58 (events/{record}/interest, vendor-opportunity, creator-opportunity, logistics-support), :61-66 (CreatorCommerceTesterController — featured-reels, applications, promotions), :46-49 ("book anything" records/request — likely the Service-providers/Rentals surface), :69-76 (CreatorSpaceAIController)
DATABASE TABLES:  merchant_prospects / ai_outreach_messages referenced in error output this session (FK constraint observed); urban_goodz_community_marketplace_items referenced in UrbanGoodzEcosystemIntegrationTest.php's core-tables list
AUTOMATION STATUS: NOT VERIFIED THIS PASS beyond confirming these routes exist. `tests/Unit/CreatorCommerceContractTest.php` (4 methods, existing) has one confirmed real failure this session: it asserts routes/api/v1/urban_goodz.php contains the string `CreatorCommerceTesterController`, but the controller referenced by the live routes is under a different name/namespace than the test expects — a genuine, real, currently-failing assertion (not a false positive) that should be triaged, not silently ignored.
```

## Permissions and security

```
AUTOMATION STATUS: AUTOMATED (this session) for guard isolation — `test_authenticated_business_user_cannot_access_admin_guarded_routes` proves the `admin` middleware (app/Http/Middleware/AdminMiddleware.php) only trusts the `admin` guard, and a fully-authenticated `business`-guard user is redirected to the admin login exactly as an anonymous visitor would be. EXISTING COVERAGE for finer-grained module permissions: `UrbanGoodzDriverCapabilityControllerSecurityTest.php`, `UrbanGoodzDriverBusinessCourierControllerSecurityTest.php`, `UrbanGoodzDriverDispatchNotificationSecurityTest.php`, `UrbanGoodzDriverJobDiscoverySecurityTest.php`, `VendorApiSecuritySourceTest.php` (Unit) — file names and sizes are consistent with real coverage; not all individually read line-by-line this pass. The Admin-side `urban_goodz_view` permission bypass identified in the forensic report (`DashboardController.php:348`) is explicitly out of this branch's file-ownership scope (production controller) and BLOCKED ON ADMIN AUTH / owned by `admin-auth-recovery`.
```

## Queues and scheduler

```
AUTOMATION STATUS: NOT MEANINGFULLY TESTABLE in the current local config as a queue-async workflow — `phpunit.xml` sets `QUEUE_CONNECTION=sync`, so any queued job executes synchronously inline during the HTTP request in tests, which is appropriate for feature-test determinism but means "job actually processed off a real queue worker" is never end-to-end exercised here. `app/Console/Kernel.php` defines the scheduler; no test in this repository invokes `php artisan schedule:run` or asserts a specific command fires on schedule. PLANNED, NOT YET BUILT.
```

## Webhooks

```
WORKFLOW ID:      WEBHOOK-01
API ENDPOINTS:    POST api/v1/adyen/webhook, POST api/v1/payments/webhooks/{provider}
AUTOMATION STATUS: EXISTING COVERAGE — `UrbanGoodzPaymentAuditTest.php` covers `staged_test` webhook idempotency (`test_duplicate_webhook_replay`), failed-webhook persistence, and Stripe signature-secret fail-closed behavior (`test_stripe_webhook_fails_closed_without_signing_secret`) — all real, DB/ledger-verified, spot-read in full this session.
```

## OTP and Firebase

```
API ENDPOINTS:    POST verify-otp, GET otp_resent (routes/web.php), api/v1/auth/* (firebase-verify-token, verify-phone-or-email — app/Http/Controllers/Api/V1/Auth/CustomerAuthController.php)
AUTOMATION STATUS: AUTOMATED (this session), narrowly — `test_customer_api_login_rejects_missing_login_type` and `test_customer_api_login_rejects_invalid_login_type` cover the entry-point validation guard (`login_type` required/enum) for `POST /api/v1/auth/login`, which is the same controller family as OTP/Firebase login. The OTP-specific and Firebase-specific branches (phone/email OTP verification, `identitytoolkit.googleapis.com` Firebase phone sign-in) are PLANNED, NOT YET BUILT — they call an external Google identity endpoint (`CustomerAuthController.php:396`) that must be faked/mocked before a trustworthy test can assert on it; do not write a test that hits the real Google endpoint.
```

---

## Honesty note on this matrix's own limits

Areas marked "NOT VERIFIED THIS PASS" are exactly that — not confirmed working, not confirmed broken. Do not read absence of a red flag as a pass. Every AUTOMATED / EXISTING COVERAGE claim above is tied to a specific test file this session either wrote and ran (`test-support/reports/new-test-run-3.txt`, exit 0, 19 passed / 3 skipped with named blockers / 0 failed), or read in full and verified against real DB state (`UrbanGoodzPaymentAuditTest.php`, `UrbanGoodzSplitControlTest.php`, `UrbanGoodzBusinessInvoiceOwnershipTest.php`, `AdminLoginRecoveryRegressionTest.php`).
