# Driver API Contract Matrix

Generated against the isolated staging server
(`urbangoodz_isolated_staging_20260723`, 242 tables) by
`scripts/audit/driver_contract_matrix.php`, which reads Laravel's **compiled
route collection** rather than grepping `routes/*.php`. That matters for the
middleware column: group-inherited middleware only exists after routes are
compiled, so a text scan understates what actually guards each endpoint.

Machine-readable output: `docs/audit/driver_api_contract_matrix.csv`
(89 route rows across 22 feature groups; **no feature group is absent**).

## How to regenerate

```bash
APP_ENV=staging php scripts/audit/driver_contract_matrix.php \
  > docs/audit/driver_api_contract_matrix.csv
```

---

## Feature coverage

| # | Feature | Base route | Guard | Ownership | Status |
|---|---------|-----------|-------|-----------|--------|
| 1 | auth-login | `POST /api/v1/auth/delivery-man/login` | `actch:deliveryman_app`, `throttle:5,1` | n/a | PRESENT |
| 2 | auth-register | `POST /api/v1/auth/delivery-man/store` | `actch:deliveryman_app`, `throttle:5,1` | n/a | PRESENT |
| 3 | auth-password-reset | `forgot-password`, `verify-token`, `reset-password` | `actch:deliveryman_app`, `throttle:5,1` | n/a | PRESENT — see DEFECT-D6 |
| 4 | profile | `GET/PUT /api/v1/delivery-man/profile`, `update-profile`, `remove-account` | `dm.api` | self via guard | PRESENT |
| 5 | online-offline | `POST /api/v1/delivery-man/update-active-status` | `dm.api` | self via guard | **FIXED — DEFECT-D1** |
| 6 | location | `record-location-data`, `last-location` | `dm.api` | self via guard | PRESENT — no validation |
| 7 | fcm-token | `POST /api/v1/delivery-man/update-fcm-token` | `dm.api` | self via guard | PRESENT |
| 8 | order-queue | `current-orders`, `latest-orders`, `all-orders` | `dm.api` | scoped to driver | PRESENT |
| 9 | order-lifecycle | `update-order-status`, `accept-order` | `dm.api` | scoped to driver | PRESENT |
| 10 | earnings | `earning-report`, `income-statement`, `driver/earnings` | `dm.api` | scoped to driver | PRESENT — no daily series |
| 11 | payouts | `get-withdraw-list`, `get-disbursement-report`, `driver/payout-history` | `dm.api` | scoped to driver | PRESENT |
| 12 | loyalty | `loyalty-point-list`, `convert-loyalty-points` | `dm.api` | scoped to driver | PRESENT |
| 13 | reviews | `delivery-man/reviews` | `dm.api` | scoped to driver | PRESENT |
| 14 | notifications | `driver/dispatch-notifications/*` | `dm.api` | scoped to driver | PRESENT |
| 15 | business-jobs | `driver/business-jobs/{jobId}/{accept,start,pickup,delivery}` | `dm.api` | `findDriverJob` | PRESENT |
| 16 | active-jobs | `driver/active-jobs/{jobId}/{start,complete,cancel,status}` | `dm.api` | `findDriverJob` | PRESENT — see DEFECT-D2/D3 |
| 17 | job-discovery | `driver/job-discovery`, `driver/opportunities/{id}/claim` | `dm.api` | scoped to driver | PRESENT |
| 18 | load-board | `driver/load-board`, `load-board/{loadId}/accept` | `dm.api` | scoped to driver | PRESENT |
| 19 | routes-planning | `driver/routes/{routeId}/{started,completed}` | `dm.api` | `findDriverJob` | PRESENT |
| 20 | certifications | `driver/certifications/{certId}/renew` | `dm.api` | scoped to driver | PRESENT |
| 21 | capability-profile | `driver/capability-profile`, `capability-summary` | `dm.api` | self via guard | PRESENT |
| 22 | vehicles | `driver/vehicles` | `dm.api` | scoped to driver | PRESENT |

**Ownership verdict:** every driver-scoped read and mutation resolves through
either the `delivery_men` guard or `findDriverJob()`, which constrains the
lookup to `assigned_delivery_man_id` / `assigned_driver_id` = the
authenticated driver. No cross-driver access path was found. Unauthorized job
ids return `404`, not `403`, so they do not confirm a job exists.

---

## Answers to the driver lane's open contracts

### BLOCKER-0 — test driver accounts: **RESOLVED**

Deterministic fixtures now exist in the isolated staging database
(`database/seeders/StagingRoleFixtureSeeder.php`):

| id | account | `active` | `application_status` |
|----|---------|----------|----------------------|
| 9001 | `staging.driver.online@fixture.invalid` | 1 (on duty) | approved |
| 9002 | `staging.driver.offline@fixture.invalid` | 0 (off duty) | approved |
| 9003 | `staging.driver.pending@fixture.invalid` | 0 | pending |

The shared password is supplied at seed time via `STAGING_FIXTURE_PASSWORD`
and is deliberately not stored in the repository. Request it out of band.

### CONTRACT-8 — arrival check-in: **CONFIRMED ABSENT**

No `arrival`, `check-in`, or `checkin` route exists anywhere in the compiled
route table. The only near match is `api/v1/items/new-arrival`, which is a
catalogue endpoint. Arrival is **not** expressible today.

### CONTRACT-9 — which id space accepts work: **ANSWERED, WITH A DEFECT**

`{jobId}` is not a single id space. `findDriverJob()` probes four tables in a
fixed order and returns the first hit:

1. `order_anywhere_requests` (`assigned_delivery_man_id`)
2. `urban_goodz_business_client_jobs` (`assigned_delivery_man_id`)
3. `urban_goodz_dedicated_routes` (`assigned_driver_id`)
4. `urban_goodz_load_board_loads` (`assigned_driver_id`)

See DEFECT-D2 — these id spaces are independent auto-increment sequences and
therefore collide.

### CONTRACT-10 — `driver_task_status` vocabulary: **ANSWERED**

`POST /api/v1/urban-goodz/driver/active-jobs/{jobId}/status` validates
`driver_task_status` against exactly:

```
en_route | picked_up | in_progress | delivered
```

Anything else returns `422`. See DEFECT-D3 — this list disagrees with the
values the backend writes itself.

### CONTRACT-12 — completion is not an earnings receipt: **CONFIRMED**

`completeJob()` writes `driver_task_status = completed` and a
`delivery_man_id` earnings row, but returns only the normalized job. There is
no payout/receipt object in the response.

---

## Defects found

### DEFECT-D1 — duty toggle 500'd for Bearer clients — **FIXED**

`DeliverymanController@activeStatus` resolved the driver with
`DeliveryMan::where(['auth_token' => $request['token']])->first()`. The
`dm.api` middleware accepts the credential from the `Authorization: Bearer`
header **or** a `token` field, but only logs the driver into the guard — it
never merges `token` back into the request. For every Bearer client (what the
driver app sends) that lookup returned `null` and the next line dereferenced
it: going on or off duty returned a 500.

Fixed to resolve from the `delivery_men` guard, with the token lookup kept as
a fallback for legacy callers. The endpoint also now accepts an **optional**
`active` boolean, making it idempotent — previously it was a blind toggle, so
a retry after a dropped response silently parked the driver offline and
starved them of dispatch. Omitting `active` preserves the old toggle
behaviour.

Covered by `tests/Feature/StagingP0/DriverActiveStatusContractTest.php`
(6 tests).

### DEFECT-D2 — `{jobId}` id spaces collide — **UNRESOLVED**

The four tables probed by `findDriverJob()` have independent
auto-increment ids. A driver assigned both `order_anywhere_requests.id = 5`
and `urban_goodz_load_board_loads.id = 5` can only ever act on the first,
because the probe short-circuits. The second job is unreachable through
`start`, `complete`, `cancel` and `status`.

Not fixed here: the correct repair is a job-type discriminator in the route
(`/active-jobs/{type}/{id}`) or a globally unique job id, both of which are
breaking API changes that need product sign-off.

### DEFECT-D3 — status vocabulary disagrees with what the backend writes — **UNRESOLVED**

`updateStatus` accepts `en_route|picked_up|in_progress|delivered`, but
`completeJob()` writes `completed` and `startJob()` writes `in_progress`.
So `completed` is a value the client can *read* but never *send*; re-sending
a job's own status after completion yields `422`.

Deliberately not "fixed" by widening the enum: allowing `completed` through
`updateStatus` would let a driver mark a job complete while bypassing the
earnings row that `completeJob()` writes. The right fix is to keep
`completed` terminal and settable only via `/complete`, and to document it —
which this matrix now does.

### DEFECT-D4 — no transition guards — **UNRESOLVED**

`updateStatus` applies any accepted value from any current state. A job can
go `delivered` -> `en_route`. There is no state machine.

### DEFECT-D5 — write endpoints without validation — **UNRESOLVED**

19 driver write routes declare no validation at all (`validation=NONE` in the
CSV), including `record-location-data`, every `active-jobs` transition, and
`load-board/{loadId}/accept`. Location in particular accepts arbitrary
payloads.

### DEFECT-D6 — driver password reset shares the vendor enumeration flaw — **UNRESOLVED**

`DMPasswordResetController` has the same shape this lane just fixed on the
vendor side: `exists:delivery_men,...` in validation, and a distinguishable
"not found" branch. It also writes to the same shared `password_resets`
table without a role scope. Out of scope for this task, which was explicitly
vendor recovery, but it is the same bug in a second place.

---

## Cross-lane input from the vendor lane

**REQUEST 1 — vendor revenue by day: CONFIRMED ABSENT.**

`GET /api/v1/vendor/earning-info` -> `StoreLogic::get_earning_data()` returns
three scalars only:

```json
{ "monthely_earning": 0.0, "weekly_earning": 0.0, "daily_earning": 0.0 }
```

(the `monthely_earning` misspelling is in the shipped payload). There is no
by-day series anywhere under `api/v1/vendor/*`, so the vendor app's
`revenueChart` has no possible data source and is correctly reported as all
zeros. A new endpoint is required; none of the existing earning routes can be
adapted without a breaking change.
