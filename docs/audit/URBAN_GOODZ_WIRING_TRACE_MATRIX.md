# Urban Goodz Wiring Trace Matrix

The detailed matrix is [URBAN_GOODZ_WIRING_TRACE_MATRIX.csv](URBAN_GOODZ_WIRING_TRACE_MATRIX.csv). It follows each priority workflow from actor and screen through route, middleware, controller, service, reads, writes, lifecycle, money effects, notifications, failure path, rollback, test evidence, and final status.

## P0 chains

| Chain | Result | Direct reason |
|---|---|---|
| Admin login | Tested locally, not browser-certified | focused PHP evidence passes; staging Playwright not executed |
| Restricted Admin authorization | Tested locally, not browser-certified | helper/middleware tests exist; browser boundary not executed |
| AI Chief of Staff | Broken | invisible; no module permission; writes on GET; undefined `Vendor` reference |
| Driver pricing | Partially wired | CRUD/persistence exists; role exposure and downstream enforcement incomplete |
| AI financial boundary | Broken | stored recommendation/approval/live/sandbox flags are ignored |
| Driver payout | Broken | mutation endpoints lack dedicated manage authorization; no external settlement call |
| Dispatcher commission | Broken | read-only list exists; wallet/config/approval/pay/ledger workflow absent |
| Adyen webhook | Broken | missing HMAC key returns valid |
| Shopper order | Blocked | no reproducible clean database/browser chain |
| External load sourcing | Blocked | partner credentials/contracts absent for most adapters |

## AI Chief of Staff route chain

`GET /admin/urban-goodz/ai-chief-of-staff`
→ route-provider authentication middleware
→ `AiOperationsController::chiefOfStaff`
→ `generateExecutiveDailyBrief`
→ `getCommandCenterSummary`
→ `runDiagnosticScan`
→ reads operational tables
→ creates/updates business needs and human action items
→ escalates overdue actions
→ attempts unresolved `Vendor::where`
→ intended Blade render.

This is neither read-only nor permission-complete. Rendering a command page must not itself perform operational mutations.

## Load pricing and payout chain

Driver policy:

`/admin/urban-goodz/driver-pricing`
→ broad Urban Goodz route permission
→ controller-specific view/manage helper
→ `urban_goodz_driver_pricing_policies`
→ `UrbanGoodzDriverPricingService::calculatePayout`
→ optional `DynamicPricingService`
→ payout result.

Dedicated-route completion:

package-status update
→ route completion
→ calculate payout
→ create `urban_goodz_driver_earnings` with `approved`
→ increment `delivery_man_wallets.total_earning`.

Order Anywhere:

quote/split calculation
→ total amount
→ driver payout policy
→ dispatcher commission resolution
→ processing reserve
→ vendor payout / Urban Goodz revenue
→ persist split values and rule snapshot on the request
→ later ledger lifecycle.

The code has useful components, but the requested equation is not one centrally governed control:

`customer/load price - driver/carrier payout - dispatcher commission - external costs - processing fees = Urban Goodz gross margin`

External costs are not modeled consistently across all services, and the central policy/approval/audit boundary is absent.

## Authorization gaps

- AI Chief of Staff has no dedicated module permission.
- Driver payout mutation endpoints rely only on `urban_goodz_view`.
- Driver pricing performs helper checks, but its dedicated permission keys are not exposed in the inspected custom-role forms.
- Dispatcher commission view permission works, but the named approval permission has no located route/controller consumer.

## Evidence rule

Rows marked `PARTIALLY WIRED` have a plausible source chain but lack full behavioral proof. Rows marked `BROKEN` contain a concrete source defect or a required chain segment that is absent. No row is promoted to staging/live without corresponding environment evidence.

## Legacy contamination chains

Three additional P0 chains were added:

1. `DatabaseSeeder` can create 5,000 fake users, an active approved fixed-credential vendor/store, a tester measurement row, and 25 synthetic loads. No production environment hard stop is proven.
2. Shopper remember-me stores the submitted password in ordinary `SharedPreferences`.
3. Shopper iOS build identity and Firebase configuration remain bound to a legacy 6amTech bundle/project.

These are direct source defects. They do not prove production has been seeded, that a device currently retains a password, or that an iOS build was published. Each claim stops at the evidence boundary.
