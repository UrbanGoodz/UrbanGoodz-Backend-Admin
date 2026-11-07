# Logistics, dispatch and route optimization certification — status: PARTIAL

Scope covered: company (tenant) isolation in the business portal, AI-logistics, Order Anywhere business dispatch and the
dispatcher portal; first-run rendering of every parameter-free business/dispatcher page; the driver-ownership rule for
dispatch; and measured quality of the route sequencing code. **Not covered:** OTR/freight load lifecycle, medical-courier
chain of custody and recurring schedules, dispatch commission/financial reconciliation, road distances (only straight-line
`haversine` was measurable here), time-window feasibility under real constraints, live re-optimization, and any device run.

## Defects found and fixed
| ID | Sev | Defect | Fix | Regression |
|---|---|---|---|---|
| L1 | **P1** | **Dispatcher portal offered, and accepted, every active driver on the platform**: other companies' fleets and vendor-owned drivers appeared in the picker (names, ids) and could be assigned to a company's load by posting their id. A company must not control another company's drivers | `DeliveryMan::scopeAssignableByCompany`: own fleet, Urban Goodz-owned, or marketplace-shared (same definition the driver-network service uses), applied to the picker and to assignment | `LogisticsTenantIsolationTest` |
| L2 | **P1** | **Order Anywhere business dispatch leaked across companies**: `where(source_type) OR where(business_client_id = <business USER id>)` returned every Order Anywhere dispatch on the platform (SQL precedence, and the wrong id), and `show($id)` returned any dispatch with `order.user` (customer details) | scoped to dispatches tagged to the company or run by its own drivers; uses the company id | same |
| L3 | **P1** | Any dispatch company with territory states configured got HTTP 500 on dispatcher load pages: the model accessor returned the raw JSON string against its `?array` return type | accessor decodes | same |
| L4 | P2 | The dispatcher's driver filter used `delivery_men.available_states`, a column no migration creates, so it 500ed for companies with a territory | filter applies only where the column exists. **Territory-based driver filtering does not work: no driver coverage data exists** | same |
| L5 | **P1** | **`business/routes/create` returned HTTP 500 for every company with no locations**, i.e. every new company: the script block used a variable defined only in the other branch | variable defined before the branch | `LogisticsPortalPagesRenderTest` (48 pages, all 200) |
| L6 | P2 | Dispatcher user update accepted any password, including one character | `min:8` | `LogisticsTenantIsolationTest` |
| L7 | **P1** | **Route sequencing produced tours 25-38% longer than optimal** (never optimal in 25 trials). Three defects in `RouteSequencingService`: (a) `findStopIndex()` ignored its stop, so 2-opt's objective counted every stop-to-stop leg as 0 miles and 2-opt could never improve anything; (b) `buildPairwiseMatrix($stops, 0)` zero-filled stop 0's row, so every edge leaving the first input stop was free; (c) the first stop was whichever was first in the input, ignoring the depot, and 2-opt pinned it | correct matrix lookup, no origin row, start at the stop nearest the depot, let 2-opt move the first stop unless it is urgent/time-windowed, add an Or-opt pass (capped at 80 stops for run time) | `RouteOptimizationQualityTest` |
| L8 | **P1** | **Any approved driver could build a run from any company's intake batch, in any state** (`routes/from-batch` looked the batch up by id alone), taking the packages and recipient details | batch must be READY_FOR_ROUTING / LOCKED_FOR_ROUTING and the driver must be one the batch's company may use; otherwise 404 | `DriverBatchToSortedRunTest` |

### Measured sequencer quality (7 stops, 25 seeded trials, brute-force optimum; straight-line distances)
| Scenario | Before: mean / worst gap, optimal found | After |
|---|---|---|
| Depot, open end | 38.3% / 81.9%, 0 of 25 | 0.1% / 1.9%, 23 of 25 |
| Depot round trip | 24.7% / 51.8%, 0 of 25 | 0.0% / 0.0%, 25 of 25 |
| No depot | 34.6% / 65.1%, 0 of 25 | 0.4% / 5.8%, 22 of 25 |
Reported miles agree with an independent calculation to 0.02%. 60 stops: 146 mi vs 756 mi in input order, 2 s; 80 stops 3 s; 200 stops 7 s (2-opt only).
Dispatcher route planning (`RoutePlanningService`) sequences every cluster with this code, so it is fixed too.

### Driver "choose where to finish" solver (`DedicatedRouteOptimizationService`, used by `routes/{id}/finish`)
Already sound; measured, not changed: mean gap to optimal 0.5% (finish at hub), 1.2% (custom address), 1.4% (open); worst 13.6%.

### Isolation that held
Business portal routes, packages, locations, users, documents, invoices, manifests, load board, AI dispatches, exceptions and copilot
recommendations were already scoped by company and survived every attack. Dispatcher load/route/user lookups were already scoped.

## Your described flow: scanned-in packages grouped by area -> driver scans -> stops ordered toward where the driver wants to finish
- **Grouping by geographic area (dispatcher):** works. `RouteGeographicGroupingTest`: three service areas become three routes, each
  from exactly one area, every package present exactly once, un-geocoded packages reported (not dropped), same-address packages kept together.
- **Driver sorts toward their finish:** works at the API level (`DriverBatchToSortedRunTest`): `routes/from-batch` builds the run from a
  scanned batch, `routes/{id}/finish` with `hub | address | open` re-sorts it, and the order changes with the finish point.
- **Gaps to confirm with the product owner:**
  1. `from-batch` turns a *whole batch* into one run; it does not split by area. Area grouping happens in the dispatcher planner. They are
     separate code paths, and nothing connects "dispatcher groups by area" to "driver builds a run from that area's packages".
  2. The driver app does not call `routes/from-batch`. Its resequence control calls the older `routes/{id}/sequence`, which can force dispatcher
     approval above 20%/15 miles variance; the newer `finish` call exists only in the app's service layer. Which one should the driver see?
  3. Distances are straight-line unless `URBAN_GOODZ_DISTANCE_MATRIX_PROVIDER` (+ ORS key) is set in production. Optimized order is then optimal
     for straight lines, not for roads.
  4. Time windows and priority are honoured by the greedy pass but not by 2-opt/Or-opt moves (pre-existing); `checkTimeWindowFeasibility` is the backstop.

## To finish
OTR/freight and medical-courier lifecycle tests (assignment, status transitions, proof, chain of custody, recurring schedules); dispatch commission
reconciliation; road-distance validation with ORS configured; time-window-aware local search; per-company staging run on a device.
