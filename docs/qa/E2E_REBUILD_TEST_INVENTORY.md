# Urban Goodz — Test Inventory & Trustworthiness Audit

Author: Claude Session 2 (branch `e2e-certification-rebuild`, worktree `AdminPanel_E2E_Rebuild`)
Date: 2026-07-23
Scope: Every test file discoverable under `tests/**` at commit `af5876e` (HEAD of this branch at audit time). No `e2e/`, `playwright/`, or `appium/` top-level directories exist in this repository — all browser tests live under `tests/Browser/` and `tests/playwright/`; **no Appium source, config, or spec files exist anywhere in the repository.**

This document independently re-verifies the findings of the forensic report supplied at the start of this session. It does not merely restate them — every classification below was produced by reading the actual test source in this checkout.

---

## 0. Top-line reality check

| Claimed (QA manifest, `docs/qa/URBAN_GOODZ_COMPLETE_TEST_MANIFEST.md`) | Actual (this audit) |
|---|---|
| "24 Total Source-Verified PHPUnit Tests" | **360 actual PHPUnit test methods** exist (304 in `tests/Feature`, 56 in `tests/Unit`), across 42 files. The manifest's 24 named methods do not match any real method name in the files it cites (see §3). |
| "32 Tests Executed, 32 Passed" (Playwright) | 32 specs in `tests/playwright/**` do exist and do run, but each one only performs `page.goto()` + `expect(page).toBeDefined()`. `page` is never undefined for any URL, including 404/500 responses, so this assertion cannot fail. 0 of the 32 verify login, role, DB state, or final outcome. |
| "352/352 PASSED" (Appium, device ZT42268MG6) | **0 Appium files exist in this repository.** No `wdio.conf.js`, no `appium` config, no `test/specs/*.spec.js` referenced by the manifest. This number has no supporting artifact of any kind in this checkout. |

---

## 1. Browser / Playwright test files — full per-test classification

### 1.1 `tests/playwright/admin-portal.spec.js` (12 tests)

All 12 tests share the identical pattern:
```js
await page.goto('<url>');
await expect(page).toBeDefined();
```
`page` is a Playwright fixture object that is never `undefined`, even for a 404, 500, or blank error page. This assertion is unconditionally true and cannot fail under any server response.

| TEST NAME | ROLE | ACTION | DB VERIFIED | FINAL STATE | TRUSTWORTHY |
|---|---|---|---|---|---|
| Admin Auth — Valid Admin Login | none (no login attempted) | navigate only | No | No | **FALSE POSITIVE** |
| Admin Auth — Rejects Invalid Password | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Auth — Invalidates Session on Logout | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Auth — Restricts Unauthorized Role Access | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Markets — Configures Houston/Multi-City Zones | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Vendors — Processes Vendor Approval | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Drivers — Verifies Capability/Medical Gates | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Dispatch — Assigns Eligible Drivers | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Load Board — Publishes Internal Loads | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Load Sourcing — Deduplicates Freight Sources | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin Dynamic Pricing — Configures Surge/Floor | none | navigate only | No | No | **FALSE POSITIVE** |
| Admin AI Chief of Staff — Daily Brief | none | navigate only | No | No | **FALSE POSITIVE** |

REASON (all 12): test name describes a workflow (e.g. "Processes Vendor Approval") that the test body never performs. No form is filled, no button is clicked, no API call is awaited, no assertion can fail. ARTIFACTS: none retained (report predates claimed run per forensic report §Test-evidence integrity).

### 1.2 `tests/playwright/business-portal.spec.js` (8 tests) — same pattern, all **FALSE POSITIVE**
Auth, Dashboard, Locations, Employees, Intake, Package Pool, Routes, Billing — identical `goto` + `toBeDefined()` shape. Two of the eight (`Business Auth`, `Business Dashboard`) navigate to `/admin/urban-goodz/ai-operations`, not to any business-portal route at all — the test name and the actual navigated URL do not correspond to the same surface.

### 1.3 `tests/playwright/dispatcher-portal.spec.js` (6 tests) — same pattern, all **FALSE POSITIVE**, and worse: the URLs don't exist

Route audit (`routes/business.php:149-172`) confirms there is **no standalone `/dispatcher/*` route at all**. The dispatcher surface is nested under the business portal: `Route::middleware(['business','dispatcher','dispatch-territory'])->prefix('dispatcher')` inside the `business/` group, meaning the real paths are `/business/dispatcher/dashboard`, `/business/dispatcher/loads`, etc., gated by business-session auth. There is no `/dispatcher/login` route anywhere in the application — dispatchers authenticate through the business login.

Every URL this spec navigates to (`/dispatcher/login`, `/dispatcher/dashboard`, `/dispatcher/loads`, `/dispatcher/assignment` [doesn't exist under any prefix], `/dispatcher/tracking` [doesn't exist], `/dispatcher/settlement` [doesn't exist]) therefore 404s (or worse) against the real application. Because the only assertion is `expect(page).toBeDefined()`, **all 6 tests pass while exercising a surface that does not exist.** This is the single clearest concrete proof in this audit that the existing Playwright suite provides zero real coverage: it isn't just shallow, it is testing routes the application never registers.

### 1.4 `tests/playwright/cross-role-e2e.spec.js` (6 tests) — same pattern, all **FALSE POSITIVE**
Every "E2E Flow" (e.g. "Vendor Product to Customer Order to Driver Delivery to Ledger Settlement") is a single `page.goto()` to one URL. No role transitions occur, no cross-role handoff is exercised, no order/ledger state is created or asserted.

**Subtotal, `tests/playwright/**`: 32/32 tests are FALSE POSITIVE. This is the exact "32/32" figure cited in the forensic report.**

### 1.5 `tests/Browser/AdminLoginTest.spec.js` (1 test)

```
TEST FILE:      tests/Browser/AdminLoginTest.spec.js
TEST NAME:      Admin Login Form Submission, Session Persistence, and Navigation
SURFACE:        Admin Portal
ROLE:           Admin
ACTION PERFORMED: Fills email/password and clicks submit, but only if the form elements are found (silently skips the entire login attempt otherwise)
ASSERTIONS:     expect(url).not.toContain('500')
DATABASE VERIFIED: No
FINAL STATE VERIFIED: No — a redirect to /login/admin with a validation error, a 403, a 404, or a successful dashboard load are ALL treated as pass
ARTIFACTS:      None captured (no trace/screenshot config in this file; screenshots are separately confirmed missing per forensic report)
TRUSTWORTHY:    FALSE POSITIVE
REASON:         (1) hardcoded credentials `admin@admin.com` / `12345678` committed to source — a direct violation of the "no hardcoded credentials" integrity requirement; (2) the pass condition is "URL does not contain the string 500", which is not a login assertion — a login page that reloads with a validation error also passes; (3) the login attempt itself is wrapped in an `if (emailInput && passwordInput && submitBtn)` guard with no `else` branch, so if the anti-bot interstitial or a layout change removes those selectors, the test silently does nothing and still passes.
```

### 1.6 `tests/Browser/admin-login.spec.js` (4 tests)

```
TEST FILE: tests/Browser/admin-login.spec.js
```
| TEST NAME | ACTION | ASSERTIONS | DB | FINAL STATE | TRUSTWORTHY | REASON |
|---|---|---|---|---|---|---|
| login page loads with UG branding | navigate, inspect DOM | title regex, CSS asset string present, email/password/submit visible | No | Partial | **INCOMPLETE** | Real DOM assertions, no fabricated pass condition — but never submits, never verifies branding beyond one CSS filename, uses `input[name="email"], input[type="email"]` generic dual-selector instead of a stable `data-testid`. |
| login rejects invalid credentials | fills wrong creds, submits, `waitForTimeout(2000)` | URL still contains "login" | No | **No** — does not check for a visible error message or that authentication truly failed server-side | **INCOMPLETE** | Real action + real assertion, but doesn't confirm rejection reason, uses arbitrary sleep instead of a deterministic wait (`waitForURL`/`waitForResponse`), no DB/session check that no admin session was created. |
| reCAPTCHA auto-detect works | navigate, inspect DOM | body contains "g-recaptcha" or the literal substring "captcha" | No | No | **FALSE POSITIVE** | The word "captcha" appears in ordinary page text/labels regardless of whether any CAPTCHA control is functional or even rendered — this assertion is nearly unfalsifiable. |
| mobile login renders correctly (viewport 375×812) | navigate | title regex, email input visible | No | No | **INCOMPLETE** | Legitimate visual-regression-lite check, but no real workflow. |

### 1.7 `tests/Browser/business-portal.spec.js` (9 tests) — the strongest browser file in the repo

| TEST NAME | ACTION | ASSERTIONS | TRUSTWORTHY | REASON |
|---|---|---|---|---|
| login page loads with UG branding | navigate, inspect DOM | brand string, CSS asset, form visible | INCOMPLETE | Real but shallow — no submission. |
| login page has welcome message | navigate, read heading text | heading contains "welcome" or "urban goodz" | INCOMPLETE | Real DOM assertion, trivial to satisfy, not a workflow. |
| login rejects invalid credentials | submits wrong creds, `waitForTimeout(2000)` | URL contains "business/login" | INCOMPLETE | Same weakness as §1.6 row 2 — real action, no confirmation of *why* it failed, arbitrary sleep. |
| forgot password link exists and navigates | click link | URL contains "business/forgot-password" | MEANINGFUL (navigation-only) | Real click + real URL assertion; correctly scoped to what it claims. |
| password toggle works | reads `type` attribute before/after click | type flips `password`→`text` | MEANINGFUL | Genuine UI-state assertion of a real control; conditionally skipped if toggle isn't visible (a soft skip that should be a hard `test.skip` with a named blocker instead of silently passing). |
| forgot password page loads | navigate | brand string, email input visible | INCOMPLETE | Shallow but honest. |
| forgot password submits email | submits real email, `waitForTimeout(2000)` | URL still contains "business/forgot-password" | INCOMPLETE | Confirms the page doesn't error, does NOT confirm an email was actually queued/sent (no mail assertion, no DB `password_resets` row check). |
| dashboard redirects to login (unauthenticated) | navigate directly to `/business/dashboard` | URL contains "business/login" | **MEANINGFUL** | This is one of the few genuinely correct security-boundary assertions in the whole browser suite. |
| mobile login renders correctly | navigate at mobile viewport | brand string present | INCOMPLETE | Shallow but honest. |

**Verdict for this file: best of the existing browser suite — real actions, no fabricated language, no hardcoded credentials — but shallow (no true login, no DB verification, sleeps instead of deterministic waits). Reusable as a foundation; extended in Deliverable 3 below.**

### 1.8 `tests/Browser/scripts/api-test.sh` — not a test; a shell script. Not classified as a test artifact.

### 1.9 `tests/playwright-report/index.html` and `tests/Browser/last-run.json`
Stale report artifacts. Per forensic report, the report predates the claimed run and does not correspond to a reproducible execution of the current spec files. Classified: **MISSING ARTIFACT** (the artifact that exists does not support the claims made about it).

---

## 2. Appium — 352 claimed tests

```
TEST FILE:       none found (searched entire repository tree for *appium*, *webdriver*, wdio.conf.*, test/specs/**)
TEST NAME:       n/a
SURFACE:         Mobile (Shopper/Vendor/Driver apps)
ROLE:            n/a
ACTION PERFORMED: n/a
ASSERTIONS:      n/a
DATABASE VERIFIED: n/a
FINAL STATE VERIFIED: n/a
ARTIFACTS:       none
TRUSTWORTHY:     FALSE POSITIVE / MISSING ARTIFACT (fabricated count)
REASON:          The QA manifest lists 16 spec files (e.g. test/specs/ai-surfaces-e2e.spec.js, .../customer-marketplace-order.spec.js) totaling 352 cases against "Device ZT42268MG6." None of these files, nor any Appium/WebdriverIO config, session log, JUnit XML, or screenshot exists anywhere in this checkout. This cannot be independently verified and must not be reported as passing, executed, or even drafted.
```

---

## 3. PHPUnit — `tests/Feature/**` and `tests/Unit/**`

Unlike the Playwright layer, this suite is **substantially real**: 360 test methods across 42 files, most using `DatabaseTransactions`, real Eloquent model creation, real service-layer calls, and real DB-state assertions (spot-checked in detail: `UrbanGoodzSplitControlTest.php`, `UrbanGoodzPaymentAuditTest.php`, `UrbanGoodzBusinessInvoiceOwnershipTest.php`, `AdminLoginRecoveryRegressionTest.php` — all four verify real database writes/reads and real final state, e.g. wallet balances, ledger rows, HTTP 404 on cross-tenant access, session error bags).

### 3.1 QA manifest mismatch (confirmed, not merely alleged)

The manifest (`docs/qa/URBAN_GOODZ_COMPLETE_TEST_MANIFEST.md`) claims, for `UrbanGoodzSplitControlTest`:
- `test_order_payout_splits_correctly_between_vendor_driver_and_platform`
- `test_split_calculation_prevents_negative_platform_margin`
- `test_split_reconciliation_records_ledger_entries`
- `test_tenant_isolation_prevents_cross_vendor_split_leakage`

**None of these method names exist in `tests/Feature/UrbanGoodzSplitControlTest.php`.** The actual methods are `test_participating_vendor_split_calculation`, `test_external_merchant_split_calculation`, `test_no_dispatcher_commission_when_no_dispatcher`, `test_dispatcher_commission_from_platform_default`, `test_dispatcher_commission_from_business_client`, `test_per_order_override_wins_over_all`, `test_financial_rules_snapshot_is_persisted`, `test_config_change_does_not_affect_existing_order`, `test_settle_splits_only_once`, `test_duplicate_refund_is_prevented`, `test_cancellation_before_authorization_reverses_pending_splits`, `test_card_purchase_does_not_modify_customer_payment_status`, `test_all_public_methods_exist`, `test_reconciliation_passes_for_balanced_splits`, `test_reconciliation_fails_for_unbalanced_splits`, `test_ai_service_has_no_split_methods`, `test_payment_ai_controller_cannot_set_split_percentages` — **17 real methods in this one file, none matching the 4 the manifest claims.**

The same divergence pattern holds for the other 5 classes the manifest names. **The manifest is not a reliable execution ledger — it names the correct class but invented plausible-sounding method names rather than reading the file.** This confirms the forensic report's finding verbatim, with the specific evidence spelled out.

Also note: the manifest additionally *undercounts* — it claims "24 Total" against an actual 360 methods. This is the opposite failure mode from the Playwright section (which overclaimed 32 meaningful tests for 32 vacuous ones) — here the manifest under-reports real, working coverage while also getting the names wrong, which makes it useless as a coverage index in either direction.

### 3.2 File-level classification (by line count / method density, 31 of 42 files individually enumerated below, remaining 11 Unit files summarized in aggregate)

| File | Methods | Lines | Class |
|---|---:|---:|---|
| UrbanGoodzPaymentAuditTest.php | 17 | 668 | MEANINGFUL (spot-verified in full) |
| UrbanGoodzSplitControlTest.php | 17 | 580 | MEANINGFUL (spot-verified in full) |
| UrbanGoodzLoadSourcingTest.php | 23 | 634 | MEANINGFUL (name/size consistent with real coverage; not line-read in full this pass) |
| ConfigControllerMapApiTest.php | 36 | 571 | MEANINGFUL (dense, not line-read in full this pass) |
| UrbanGoodzIntakeBatchTest.php | 11 | 462 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzDriverSequencingTest.php | 8 | 447 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzEcosystemIntegrationTest.php | 44 | 419 | MEANINGFUL, with one weak smoke test — `test_database_connection_works` is `DB::connection()->getPdo(); $this->assertTrue(true);`, which only proves the PDO connection didn't throw (a real but very weak check, not a fabricated pass). Other methods (e.g. `test_ai_operations_admin_route_uses_the_canonical_name`) make real route/middleware assertions. |
| UrbanGoodzOrderAnywhereAiDispatchTest.php | 10 | 406 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzAIExecutionEngineTest.php | 18 | 345 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzAiWorkforceTest.php | 7 | 318 | INCOMPLETE — `test_outreach_draft_requires_approval_and_no_real_smtp` has an `if ($msg) {...} else { $this->assertTrue(true); }` escape hatch: if the service returns null for any reason (bug or intended), the test still passes without ever exercising its stated purpose (asserting the draft stays in `draft` status and is never sent). Should assert `$msg` is non-null with a clear reason, not fall back to a tautology. |
| UrbanGoodzRouteClusteringTest.php | 2 | 298 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzAiCopilotTest.php | 3 | 286 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzAiAuditTest.php | 5 | 246 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzWithdrawalSecurityTest.php | 3 | 172 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzNotificationDeliveryTest.php | 3 | 177 | MEANINGFUL (not line-read in full this pass) |
| ServiceBookingMigrationSafetyTest.php | 12 | 151 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzDriverNotificationBehavioralTest.php | 8 | 139 | MEANINGFUL (not line-read in full this pass) |
| AdminLoginRecoveryRegressionTest.php | 5 | 119 | **MEANINGFUL — verified in full.** Real request submission, real session/DB assertion, real file-content route-name assertions. See §5 (coordination note — this file already covers CAPTCHA-rejection-path and route-repair regressions; do not duplicate). |
| UrbanGoodzDriverDispatchNotificationProducerTest.php | 7 | 119 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzDriverCapabilityControllerSecurityTest.php | 4 | 108 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzDriverVehicleTrailerCapabilityTest.php | 9 | 104 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzDriverDispatchNotificationSecurityTest.php | 7 | 98 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzDriverJobDiscoverySecurityTest.php | 7 | 96 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzAiMigrationTest.php | 1 | 87 | INCOMPLETE (single method for a file named "migration" — worth a follow-up read) |
| UrbanGoodzAgeComplianceRuntimeTest.php | 7 | 74 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzBusinessInvoiceOwnershipTest.php | 1 | 62 | **MEANINGFUL — verified in full.** Single, well-targeted tenant-isolation test with real DB rows and a real 404 assertion. |
| UrbanGoodzDriverBusinessCourierControllerSecurityTest.php | 3 | 58 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzSmtpDispatchTest.php | 1 | 50 | INCOMPLETE (single method, worth a follow-up read) |
| UrbanGoodzLoadBoardTest.php | 4 | 44 | MEANINGFUL (not line-read in full this pass) |
| UrbanGoodzLoadBoardWorkflowTest.php | 20 | 281 | MEANINGFUL (not line-read in full this pass) |
| ExampleTest.php (Feature) | 1 | 21 | MEANINGFUL but trivial — Laravel scaffold default (`GET /` redirects to `https://urbangoodzdelivery.com`); low value, keep as smoke check only |

All 11 `tests/Unit/*.php` files (56 methods total) were not individually re-verified this pass; they are unit-level (service/contract) tests rather than E2E and are out of scope for the browser-workflow rebuild, but should be included in the next PHPUnit run to get real PASS/FAIL numbers (see §6).

The two files flagged by the weak-assertion grep were read in full and reclassified above: both are genuine tests with one weak assertion each, not fabricated passes. No swallowed exceptions (`catch(){}`) were found anywhere in `tests/**` in this checkout.

---

## 4. Summary counts

| Category | Count |
|---|---|
| Playwright spec files found | 7 (`tests/playwright/*.spec.js` ×4, `tests/Browser/*.spec.js` ×3) |
| Playwright/Browser test cases found | 46 |
| — FALSE POSITIVE | 34 (32 in `tests/playwright/**` + `AdminLoginTest.spec.js` + `admin-login.spec.js` "reCAPTCHA auto-detect works") |
| — INCOMPLETE | 9 (3 in `admin-login.spec.js` + 6 in `tests/Browser/business-portal.spec.js`) |
| — MEANINGFUL | 3 (`forgot password link exists and navigates`, `password toggle works`, `dashboard redirects to login` — all in `tests/Browser/business-portal.spec.js`) |
| Appium spec files found | 0 (352 claimed, unverifiable, not to be repeated as fact) |
| PHPUnit test methods found | 360 (304 Feature + 56 Unit) across 42 files |
| PHPUnit methods spot-verified MEANINGFUL in full | 4 files / 40 methods |
| PHPUnit files flagged NEEDS REVIEW | 2 (`UrbanGoodzEcosystemIntegrationTest.php`, `UrbanGoodzAiWorkforceTest.php`) |
| QA manifest PHPUnit entries confirmed to not match real method names | 24/24 |

No test count in this document is invented — every number above was produced by `grep -c "public function test_"` / direct file reads against this checkout, not carried over from any prior manifest.

---

## 5. Coordination note (Admin auth branch)

`AdminLoginRecoveryRegressionTest.php` (5 methods, 119 lines, `tests/Feature/`) was read in full this pass and already covers: rejection of an invalid custom CAPTCHA answer, the root-route redirect target, dashboard route-name assertions, and file-content checks that the approved login-page assets are referenced correctly. It exists on this branch already (not authored this session) and pre-dates the CAPTCHA fail-open / permission-bypass defects called out in the forensic report at `DashboardController.php:348`.

This branch (`e2e-certification-rebuild`) does not modify, extend, or duplicate that file, and does not touch `LoginController.php`, `DashboardController.php`, CAPTCHA verification, or any other Admin-authentication source — that is explicitly the responsibility of a separate `admin-auth-recovery` branch. Any future session adding Admin-login test coverage should: (a) check whether `admin-auth-recovery` has merged its fix first, since testing against the known-broken CAPTCHA fail-open would either bake in wrong expected behavior or need to be rewritten immediately after; and (b) diff against `AdminLoginRecoveryRegressionTest.php`'s existing 5 methods before adding new ones, to avoid duplicate coverage.

## 6. Real PHPUnit run results (root-cause breakdown, not assumed)

Every number below comes from `test-support/reports/phpunit-full.txt` (see `test-support/reports/RUN_METADATA.md` for the exact command, SHA, and environment) — read entry-by-entry, not aggregated by guesswork. `--no-coverage`, local MySQL `urbangoodz_test`.

```
Tests: 382, Assertions: 1027, Errors: 112, Failures: 7, PHPUnit Deprecations: 2, Skipped: 3.
```

360 of the 382 cases are the pre-existing suite (§3); 22 are this session's new `UrbanGoodzPublicSurfaceValidationBoundaryTest.php` (21 methods; `test_public_informational_pages_render_the_configured_landing_view` runs as 2 cases via its data provider).

### 6.1 The 112 errors — three distinct, unrelated root causes

An earlier draft of this document lumped 94 of these into a single "schema drift" bucket. That was wrong: 2 of those 94 are a test-code ordering defect that would fail against *any* correctly-migrated database, not just this drifted local one — corrected below after being independently re-verified by reading the two offending test files directly, not assumed from the error message alone.

| Count | Root cause | Evidence |
|---:|---|---|
| 18 | **Test-code defect** — missing import. `tests/Feature/UrbanGoodzAIExecutionEngineTest.php:55` calls the `DB` facade without a `use Illuminate\Support\Facades\DB;` import; inside the `Tests\Feature` namespace, the bare `DB::` reference resolves to the nonexistent class `Tests\Feature\DB`, so every method in that file that reaches line 55 throws `Error: Class "Tests\Feature\DB" not found`. | All 18 entries name `UrbanGoodzAIExecutionEngineTest.php:55`. Fix is a one-line import add — not attempted this session. |
| 92 | **Local database schema drift.** The local `urbangoodz_test` MySQL database is missing columns that current migrations define. Column-not-found `SQLSTATE[42S22]` errors observed for: `phone` (delivery_men, ×60 combined `where`/`field list`), `email` (delivery_men, ×8), `image` (admins, ×9), `provider` (`urban_goodz_load_board_loads`, ×10), `intake_batch_id` (`urban_goodz_route_packages`, ×3), `customer_price` (×1), `admin_review_required` (×1). | Fixable by running current migrations against `urbangoodz_test` — not attempted this session; this is shared local infrastructure and migrating it destructively without confirming no one else depends on its current state was judged out of scope for a single pass. |
| 2 | **Test-code FK-ordering defect — NOT schema drift.** Two tests tear down/reset tables in an order that violates foreign keys the *currently committed* schema intentionally defines, so these would fail identically on a fully, correctly migrated database: `tests/Feature/UrbanGoodzAiCopilotTest.php:207` calls `UrbanGoodzRoutePackage::truncate()` directly, but `urban_goodz_medical_custody_logs.package_id` (FK `ug_cust_pkg_fk`) references that table, so MySQL rejects the truncate (`SQLSTATE[42000] ... 1701 Cannot truncate a table referenced in a foreign key constraint`). `tests/Feature/UrbanGoodzAiMigrationTest.php:33` calls `Schema::dropIfExists('merchant_prospects')` without first dropping/handling `ai_outreach_messages.merchant_prospect_id` (FK `ai_outreach_messages_merchant_prospect_id_foreign`), which references it, so the drop is rejected. | Read both test files directly at the cited lines to confirm the FK relationship is intentional in the current schema, not itself missing/drifted. Fix is test-code (truncate/drop child tables first, or wrap in `Schema::disableForeignKeyConstraints()`/`enableForeignKeyConstraints()`), unrelated to migrating `urbangoodz_test` — not attempted this session. |

**`php artisan migrate` against `urbangoodz_test` would address at most the 92 column-not-found errors above (and the 2 related failures in §6.2) — 94 error+failure entries total, not 96 and not "the 112 errors." It would not touch the 18 `DB`-import errors, the 2 FK-ordering test-code defects, or the 3 Passport-key failures below**, all three of which need their own separate, unrelated fixes.

### 6.2 The 7 failures — three distinct root causes

| # | Test | Cause |
|---|---|---|
| 1 | `Tests\Unit\CreatorCommerceContractTest::test_file_backed_tester_routes_are_removed` | Genuine, pre-existing test/source mismatch — asserts `routes/api/v1/urban_goodz.php`'s contents do **not** contain the string `CreatorCommerceTesterController`; the route file's actual `CreatorCommerceTesterController` routes (confirmed live at `routes/api/v1/urban_goodz.php:61-66`) still exist, so the assertion fails. Not schema/environment related — a real, currently-failing check that needs a product decision (route removal vs. test update), not a test-integrity fix. |
| 2 | `Tests\Feature\ExampleTest::test_example` | Stale pre-existing assertion — expects `GET /` to redirect to `'https://urbangoodzdelivery.com'`; the app's real, current behavior (confirmed by direct route/controller read) is a redirect to `route('login', ['tab' => 'admin'])`, i.e. `/login/admin`. This is the Laravel scaffold default test, never updated after the login-redirect behavior changed. Not schema/environment related. |
| 3 | `Tests\Feature\UrbanGoodzEcosystemIntegrationTest::test_route_packages_table_has_required_status_columns` | Schema drift — asserts `urban_goodz_route_packages.business_client_id` exists via `Schema::hasColumn()`; it does not in the local `urbangoodz_test` DB. Same root cause as §6.1's 92 column-not-found errors. |
| 4 | `Tests\Feature\UrbanGoodzEcosystemIntegrationTest::test_customer_config_api_responds` | Schema drift — expects HTTP 200, gets 500 from an uncaught `PDOException: Unknown column 'type' in 'where clause'`. Same root cause as §6.1's 92 column-not-found errors. |
| 5 | `Tests\Feature\UrbanGoodzEcosystemIntegrationTest::test_service_bookings_api_requires_auth` | **Local Passport OAuth keys not generated** — `storage/oauth-private.key` / `oauth-public.key` don't exist in this worktree (`php artisan passport:keys` was never run here), so any request hitting an `auth:api`-guarded route throws `LogicException: Invalid key supplied` in `league/oauth2-server`'s `CryptKey`, surfacing as a 500 instead of the expected 401. A local setup gap, not schema drift and not a test-code defect. |
| 6 | `Tests\Feature\UrbanGoodzEcosystemIntegrationTest::test_urban_goodz_app_config_requires_auth` | Same Passport-key cause as #5. |
| 7 | `Tests\Feature\UrbanGoodzEcosystemIntegrationTest::test_fashion_fit_scan_api_requires_auth` | Same Passport-key cause as #5. |

Net, of all 119 error+failure entries:

| Root cause | Errors | Failures | Total |
|---|---:|---:|---:|
| Local schema drift (missing columns) | 92 | 2 | **94** |
| Missing `use DB;` import (test-code) | 18 | 0 | 18 |
| FK-ordering defect, current schema (test-code) | 2 | 0 | 2 |
| Passport keys never generated (local env) | 0 | 3 | 3 |
| Stale pre-existing assertion (test-code) | 0 | 2 | 2 |
| **Total** | **112** | **7** | **119** |

None of these were introduced by this session's new test file, which contributed 0 errors and 0 failures (19 passed, 3 explicitly skipped with named blockers).
