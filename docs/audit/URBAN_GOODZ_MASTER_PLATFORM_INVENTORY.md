# Urban Goodz Master Platform Inventory

Audit basis: `6937e5d5c8d7bbf0c6405eda8a6c1d0e922033c9` on `codex-full-platform-audit-sprint`.

This is a source-and-evidence inventory, not a live certification. No production connection, database, migration, cache operation, dependency install, application build, or browser run was used. The machine had 5.56 GB free, so the low-disk prohibition remained active.

## Evidence scale

| Score | Meaning |
|---:|---|
| 0 | absent from inspected source |
| 1 | placeholder or mock |
| 2 | backend/source exists |
| 3 | UI and backend appear connected |
| 4 | focused local behavioral evidence exists |
| 5 | staging evidence exists |
| 6 | current production evidence exists |

The row-level source of truth is [URBAN_GOODZ_MASTER_PLATFORM_INVENTORY.csv](URBAN_GOODZ_MASTER_PLATFORM_INVENTORY.csv). Scores never inherit upward: source presence does not prove staging or production behavior.

## Repository census

| Area | Count |
|---|---:|
| Route files | 16 |
| Controllers | 267 |
| Models | 270 |
| Migrations | 337 |
| Blade views | 1,048 |
| Feature test files | 31 |
| Unit test files | 11 |
| Browser specs | 8 |
| App jobs | 4 |
| Console commands | 20 |

The generated static census contains 19,421 UI-control rows across seven surfaces and 285 inferred database-table rows. These are discovery counts, not behavioral pass counts.

The master inventory now contains 118 feature rows: 98 have source at score 2 or higher, 68 have a plausible UI/backend chain at score 3 or higher, 6 have focused local evidence at score 4 or higher, and none has staging or current-live certification.

## Platform truth by surface

| Surface | What exists | Current truth |
|---|---|---|
| Public website | routes, Blade pages, catalog/location controls | partially wired; current live behavior unverified |
| Admin portal | large Urban Goodz route/view/controller estate | partially wired with P0 authorization, AI, payment, and financial-control defects |
| Business Portal | authentication, routes, packages, invoices, load board, dispatcher workspace | partially wired; no full database-backed browser proof |
| Vendor web | orders, catalog, wallet, reports | partially wired; settlement and cross-role flows unverified |
| Shopper app | broad legacy app plus Urban Goodz screens | several Urban Goodz surfaces are explicit placeholders; no current APK evidence |
| Vendor app | small Flutter shell | dominated by mock repositories; debug APK is not production certification |
| Driver app | small Flutter shell | earnings/jobs/dashboard are dominated by mock repositories; some code points to production API |
| Payments | provider manager, ledgers, split logic | Adyen validation is fail-open when HMAC is absent; live credentials and reconciliation unproven |
| Load sourcing | internal/manual/email plus selected partner adapters | DAT/Truckstop need credentials; five other adapters intentionally fail closed |
| Notifications/realtime/queues | source exists | production worker, transport credentials, and device delivery unproven |

## AI Chief of Staff P0 finding

This surface is distinct from AI Copilot, Urban Goodz Genie, dynamic pricing, load sourcing, driver matching, demand forecasting, and shopper personalization.

**AI CHIEF OF STAFF EXISTS:** Yes in source: route, controller method, service, view, seed label, and tests exist.

**VISIBLE IN ADMIN:** No. No `ai-chief-of-staff` or route-name reference exists in the Admin sidebar.

**MENU LOCATION:** None. The adjacent visible item is “AI Ops Copilot,” which is a different feature.

**ROUTE:** `GET /admin/urban-goodz/ai-chief-of-staff`, named `admin.urban-goodz.ai-chief-of-staff`.

**PERMISSION:** Only the route-provider middleware stack `web`, `admin`, `current-module`, `actch:admin_panel`. There is no `module:*` permission on this route and no controller permission check.

**CONTROLLER:** `App\Http\Controllers\Admin\AiOperationsController::chiefOfStaff()`.

**AI PROVIDER:** None in the routed service.

**MODEL:** None in the routed service. The implementation is database rules and counts, not an LLM call.

**PROMPTS/TOOLS:** None in `AiChiefOfStaffService`. It does not orchestrate the adjacent AI services.

**DATA SOURCES:** `ai_tasks`, `ai_approvals`, `business_needs`, `human_action_items`, `merchant_prospects`, `orders`, and intended vendor application data. The inventory check is hard-coded to `5`, not queried.

**ACTIONS AVAILABLE:** Summary/brief reads; create or update driver-shortage and low-inventory business needs; create or update late-delivery and vendor-review human actions; escalate overdue actions.

**APPROVAL REQUIRED:** No approval gate is applied before those writes. The page links to an approvals queue, but that does not guard `runDiagnosticScan()`.

**DATABASE TABLES:** Reads and writes the tables above. `runDiagnosticScan()` is invoked by a GET page request.

**QUEUE/JOBS:** None in the routed Chief of Staff flow.

**AUDIT LOG:** None in the routed service for diagnostic creates/updates/escalations.

**USAGE/COST TRACKING:** None, consistent with no model/provider call.

**ERROR HANDLING:** None around the route’s three service calls. The service references `Vendor::where(...)` without importing or defining `Vendor` in its namespace, producing a runtime class-resolution defect.

**TESTED:** A feature test directly exercises summaries/briefs. The browser spec only asserts that the Playwright `page` object is defined after navigation, so it cannot prove HTTP success, rendered content, permissions, or writes.

**LIVE OPERATIONAL:** Unproven.

**BLOCKERS:** No menu entry; no dedicated permission; unsafe writes on GET; undefined `Vendor` reference; synthetic inventory signal; no provider/model/tool integration; no audit log, job boundary, or approval enforcement; no trustworthy browser or live evidence.

### Required action taxonomy

| Class | Permitted behavior |
|---|---|
| READ-ONLY INSIGHT | Query and summarize operational data without mutation |
| RECOMMENDED ACTION | Persist a recommendation record only, with evidence and risk classification |
| ADMIN-APPROVED ACTION | Execute only after explicit, attributable approval and revalidation |
| AUTOMATED SAFE ACTION | Allowlisted, reversible, bounded, idempotent, audited, and never financial/security-sensitive |
| PROHIBITED SENSITIVE ACTION | Direct balance, payout, ledger, credential, permission, deletion, or irreversible external mutation |

The current implementation does not enforce this taxonomy.

## Pricing and payouts P0 finding

Confirmed Admin source surfaces:

- `/admin/urban-goodz/driver-pricing`
- `/admin/urban-goodz/driver-payouts`
- `/admin/urban-goodz/driver-earnings`
- `/admin/urban-goodz/payments/*`
- `/admin/urban-goodz/order-anywhere`

`/admin/order-anywhere/settings` does **not** exist in this SHA. The Admin Order Anywhere route is `/admin/urban-goodz/order-anywhere`.

The driver-pricing controller supports policy types for marketplace delivery, courier parcel, business routes, dedicated routes, logistics loads, medical courier, Order Anywhere, and returns/exceptions. It does not provide a unified customer price, carrier payout, dispatcher commission, external-cost, processing-fee, and gross-margin control center for loads, packages, rentals, creators, services, and business billing.

Persistence is present for `urban_goodz_driver_pricing_policies`; quote split persistence is present on `order_anywhere_requests`; payout/earning/ledger models exist. End-to-end propagation to every checkout, quote, invoice, wallet, settlement, and ledger is not proven.

Critical boundary defects:

1. `UrbanGoodzDriverPricingService::calculatePayout()` uses an AI result when `payout_model=dynamic_ai`.
2. It does not enforce `recommendation_only`, `auto_apply_within_limits`, `dispatcher_approval_required`, `admin_approval_required`, `live_pricing_enabled`, or `sandbox_pricing_enabled`.
3. Dedicated-route completion records the result as `approved`; `recordEarning()` immediately increments `delivery_man_wallets.total_earning` for approved/paid earnings.
4. Driver payout approve/pay/reject routes have only the broad `module:urban_goodz_view` group. Their controller methods do not check the dedicated payout-manage permission.
5. The dedicated driver-payout permission strings are used by the pricing controller/menu but are absent from the inspected custom-role create/edit permission UI.
6. Marking a payout “paid” creates an outbound ledger row but does not invoke an external payout processor; database status is not settlement proof.

Dispatcher workspace truth:

- Commission totals and history exist at `/business/dispatcher/commissions`.
- Commission rows are created when a dispatcher assigns a driver.
- `dispatch_commissions_view` is enforced for display.
- No commission wallet, payout-history transaction model, commission configuration screen, admin approval/pay endpoints, or final ledger-adjustment workflow was located.
- A `dispatch_commissions_approve` capability name exists on the business user model, but no endpoint consumes it.

## Mobile and automated-test truth

- Shopper: package `com.urbangoodz.customer`, version `3.9.0+5`; multiple Urban Goodz screens explicitly say placeholder, waitlist, or coming soon.
- Vendor: package `com.urbangoodz.urban_goodz_vendor`, version `1.0.0+1`; dominant operational repositories are mocks.
- Driver: package `com.urbangoodz.urban_goodz_driver`, version `1.0.0+1`; dominant dashboard/job/earnings data is mocked.
- Appium’s current JUnit artifact reports 22 tests with 17 failures, not a complete passing platform certification.
- Admin authentication’s focused evidence is 33 tests and 134 assertions passing. The full suite remains at 112 errors and 7 failures on both baseline and patched evidence sets. Playwright staging was not executed.

## Legacy 6amMart contamination truth

The dedicated [6amMart contamination audit](URBAN_GOODZ_6AMMART_CONTAMINATION_AUDIT.md) and its 53-row component register distinguish required compatibility foundations from runtime defects.

Do not globally delete or rename the inherited route/controller/model/middleware foundation, module-permission helper, Tax project discriminator, or Shopper `sixam_mart` package namespace. They remain active compatibility components.

P0 exceptions include:

- the default seeder invoking fake-user, fixed-credential test-vendor, live-tester, and synthetic-load seeders;
- plaintext Shopper password persistence in `SharedPreferences`;
- legacy Shopper iOS bundle/Firebase identity;
- hard-coded realtime key and LAN fallback;
- customer-facing `6am Mart` mail fallbacks;
- legacy SQL imports containing old branding and URLs;
- unproven live branding, duplicate-deployment, and cache identity.

The Vendor and Driver standalone apps contain no direct 6amMart identifier in inspected source, but they duplicate inherited operational domains and currently rely heavily on mock repositories.

## Highest-priority blockers

1. Adyen webhook accepts events when its HMAC key is empty.
2. AI-derived payout values can flow into approved earnings and wallet totals without enforcing stored approval/sandbox/recommendation controls.
3. Driver payout mutation endpoints lack dedicated manage authorization.
4. AI Chief of Staff is invisible, has no dedicated permission, writes on GET, and contains a runtime class-resolution defect.
5. Submitted Admin passwords are placed in an encrypted client cookie by the current Claude-owned authentication code; this audit did not edit that ownership area.
6. The committed migration chain cannot create the foundational `orders` table from scratch.
7. Mobile apps and browser evidence do not establish production readiness.
8. Default database seeding is unsafe for a non-disposable environment.
9. Shopper credential storage, iOS identity/Firebase, and realtime defaults block mobile certification.
10. Current live branding records, deployed copy, and cache identity remain unproven.
