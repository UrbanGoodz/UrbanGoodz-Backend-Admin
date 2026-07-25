# Urban Goodz — Production Readiness Master Matrix

PM baseline: 2026-07-25. `PRODUCTION_READY = FALSE`.

**Scope honesty statement.** This matrix contains only rows backed by evidence observed directly on 2026-07-25. The full control-level inventory (every button, form, dropdown, and API-triggering control across Shopper, Vendor, Driver, Admin, Business, and Dispatcher) is **NOT_AUDITED** and is not fabricated here. Its discovery method and scaffold live in `URBAN_GOODZ_COMPLETE_CONTROL_INVENTORY.md`; population is assigned work, not completed work.

Machine-readable rows: `urban_goodz_production_readiness_master_matrix.csv`.

## P0 blockers

### P0-1 — Distributed tester APKs predate every mobile P0 fix
The three tester APKs were built 2026-07-22 from `c633cec` (Vendor/Driver) and `663f4db` (Customer). Every P0 recovery commit landed 2026-07-23/24 **after** those builds.

Verified by `git merge-base --is-ancestor`: `c633cec` is an ancestor of both `claude-vendor-p0-recovery` and `claude-vendor-driver-p0-recovery`. Commits shipped-to-now: Vendor 5, Driver 3, Shopper 4.

Mock authentication confirmed **present in the shipped Vendor APK source** at `c633cec:vendor_app/lib/screens/vendor_onboarding_screen.dart` lines 62, 102, 103 — a 700 ms `Future.delayed` and a hardcoded `auth.email.value = 'vendor@urbangoodz.com'`.

**Consequence for the evidence file:** `outputs/final-release-evidence/vendor-live-check.txt` records `Vendor Auth: SUCCESS (22/22 startup & auth assertions verified)`. That run exercised the mock login path, not real backend authentication. The assertions are real; what they prove is not vendor authentication. The same caution applies to the Driver `Auth: SUCCESS` line — the real-login commit `e6dcf06` came after the build.

Severity **P0**. Any tester currently on these builds is testing mock auth and fabricated success states.

### P0-2 — 34 backend commits committed locally but never deployed
Deployed SHA `3037ce7e` is an ancestor of local branch head `af5876e5`; `git rev-list --count 3037ce7e..af5876e5` = **34**.

Undeployed commits include admin-login-critical repairs: `1e1d19f` repair login and dashboard route references, `d5e4820` break infinite 302 redirect loop, `a4ed5fe` remove undefined `Helpers::module_permission_check` causing 500 on non-role-1 admin logins, `ebb7fbb` serve admin from Laravel public root.

Production is running code older than every one of those fixes. Whether the deployed build exhibits the bugs they fix is **unverified** — it requires an authenticated admin login against production, which has not been performed. Severity **P0**.

### P0-3 — Shopper P0 fixes exist only on this workstation
`claude-shopper-p0-recovery` @ `33e8c4b` has never been pushed to origin. Four commits removing fabricated success states (Book Services, Earn Money, Order Anywhere logout, plus 6 preview screens) exist on one disk with no remote copy. Severity **P0** (data-loss risk, not a functional defect).

### P0-4 — Backend P0 test suite has never been run
Auth, authz, marketplace, money, and document-privacy tests have not executed against any isolated database. No staging environment has been booted. There is currently **no evidence** that backend authorization or money handling behaves correctly. Severity **P0**.

### P0-5 — Vendor password-reset account enumeration
`VendorPasswordResetController` returns distinguishable responses for existing vs non-existent accounts. Identified but unfixed; assigned to Lane 3 task 5. Severity **P0** (security).

### P0-6 — Driver marketplace delivery lifecycle incomplete
~332 lines uncommitted and untested in Lane 2. Delivery lifecycle transitions are not proven end to end. Severity **P0** (core workflow).

## P1 blockers

| ID | Item | Evidence | Owner |
|---|---|---|---|
| P1-1 | `revenueChart` never populated by `fetchDashboard`; no endpoint supplies vendor revenue-by-day. All vendors see an empty chart. | Lane 1 report | Lane 1 → Lane 3 contract |
| P1-2 | Driver `BACKEND_CONTRACTS.md` requests unresolved | `76de2f8` | Lane 3 |
| P1-3 | Vendor branch commit ordering: tests `945e4e9` precede source `ca6f790`; that commit is not independently green | `git log` | Accepted — no rebase |
| P1-4 | 15 untracked audit documents in `UrbanGoodz2026-Revised` working tree | `git status` | PM |
| P1-5 | Windows `generated_plugin_*` CRLF churn on every flutter invocation; needs `.gitattributes` | Lanes 1–2 | PM |

## Live production surface — verified 2026-07-25

| Surface | URL | Result | Status |
|---|---|---|---|
| Root | `/` | 302 → `/login/admin` | VERIFIED |
| Admin login | `/login` | 302 → `/` | VERIFIED |
| API config | `/api/v1/config` | 200, JSON body with business config | VERIFIED |
| Business login | `/business/login` | 200 | VERIFIED |
| Dispatcher login | `/dispatcher/login` | 302 → `/` | VERIFIED |

Unauthenticated reachability only. **No authenticated session, role-permission, checkout, payment, or workflow behaviour has been verified on production.**

## Release gates

| Gate | Value | Basis |
|---|---|---|
| LOCAL_CERTIFICATION_COMPLETE | **FALSE** | Backend P0 tests never run; Driver lifecycle incomplete |
| BACKEND_DEPLOYED | **FALSE** | 34 commits undeployed |
| READY_FOR_INITIAL_TESTERS | **FALSE** | Distributed APKs contain mock auth (P0-1) |
| WAVE1_DISTRIBUTED | UNKNOWN | No Firebase distribution evidence observed |
| WAVE1_ACTIVE | FALSE | — |
| FULL_ECOSYSTEM_READY | FALSE | — |
| PRODUCTION_RELEASE_CANDIDATE | FALSE | — |
| PRODUCTION_READY | **FALSE** | — |
