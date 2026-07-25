# Urban Goodz — Production Dashboard

Last PM update: 2026-07-25

## CURRENT RELEASE GATE
`LOCAL_CERTIFICATION_COMPLETE = FALSE` — earliest open gate. `PRODUCTION_READY = FALSE`.

## ACTIVE AGENTS
Three, all relaunched 2026-07-25 after an accidental stop, all running with blanket work authorization.

| Lane | Current task |
|---|---|
| Lane 1 — Vendor mobile | Finish `BACKEND_CONTRACTS.md`, write DCP for `945e4e9..c2ddf50`, build proof, push |
| Lane 2 — Driver mobile | Recover ~332 uncommitted lines, finish marketplace delivery lifecycle, commit in chunks, test, push |
| Lane 3 — Backend/DB | Credential-scan and push `00f3769`, then staging → fixtures → backend P0 tests → enumeration fix → contract matrix → orders baseline |

**Unstaffed:** Production Operations (Phase 5 gate). **Unowned:** Shopper app.

## P0 BLOCKERS
1. Distributed tester APKs predate every mobile P0 fix; Vendor APK ships mock authentication
2. 40 backend commits committed, never deployed (admin login repairs + fail-closed CAPTCHA, module authorization, admin login error normalization)
3. `claude-shopper-p0-recovery` never pushed — exists on one disk only
4. Backend P0 test suite (auth, authz, marketplace, money, document privacy) has never been run
5. Vendor password-reset account enumeration unfixed
6. Driver marketplace delivery lifecycle incomplete and untested

## P1 BLOCKERS
`revenueChart` has no backing endpoint · Driver backend contracts unresolved · 15 untracked audit docs in the mobile repo · CRLF churn on Windows generated plugin files

## AWAITING EVIDENCE
Backend P0 test results · staging boot · Driver lifecycle transitions · Vendor build/device proof · complete control inventory (**not started**) · all 9 cross-role E2E workflows (**none executed**) · payment configuration audit · notification delivery evidence · backup restore test · monitoring status

## AWAITING OWNER DECISION
OD-1 pull stale tester builds · OD-2 approve backend deploy · OD-3 staff Production Ops · OD-4 assign Shopper owner
(full register in `URBAN_GOODZ_OWNER_DECISIONS_REQUIRED.md`)

## COMMITS / PUSH STATUS

| Branch | HEAD | Pushed |
|---|---|---|
| `claude-vendor-p0-recovery` | `c2ddf50` | YES |
| `claude-vendor-driver-p0-recovery` | `76de2f8` | YES (+ uncommitted work in flight) |
| `claude-database-staging-recovery` | `00f3769` | **NO — ahead by 1** |
| `claude-shopper-p0-recovery` | `33e8c4b` | **NO — never pushed** |
| `adminpanel-v39-backend-sprint` | `46f2cc1` | pushed; **40 commits undeployed** |

## DEPLOYMENTS
Production `3037ce7e`, last deployed 2026-07-22. Nothing deployed since. No deployment planned without OD-2.

## TEST RESULTS

| Suite | Result | Basis |
|---|---|---|
| Vendor Flutter | 50 passed / 0 failed | agent-reported at `c2ddf50`; re-verification in flight |
| Driver Flutter | 54 passed / 0 failed | agent-reported at `76de2f8`; excludes uncommitted work |
| Shopper Flutter | not run | — |
| Backend PHPUnit | **never run** | — |
| Playwright (web) | **never run** | — |
| Appium (device) | **never run** | — |

## LIVE PRODUCTION SURFACE — verified 2026-07-25
`/` → 302 `/login/admin` · `/login` → 302 `/` · `/api/v1/config` → 200 JSON · `/business/login` → 200 · `/dispatcher/login` → 302 `/`
Unauthenticated reachability only. No authenticated, permission, checkout, payment, or workflow behaviour verified on production.

## NEXT INTEGRATION ACTION
Push `claude-shopper-p0-recovery` (closes P0-3, no dependencies, one command).

## RISKS
- Testers are validating mock authentication and fabricated success states — feedback gathered now is not trustworthy
- Backend money and authorization paths have zero test evidence
- Rollback is documented but **never proven** — the 2026-07-22 backup has not been test-restored
- Queue connection is `sync`; every job runs in-request. Confirm this is intended under production load
- Phase 5 has no owner

## ROLLBACK STATUS
Backup exists at `/home/urbakkej/backups/urban_goodz_deploy_20260722_074053`. **Restore procedure unproven.** Must be tested before any deployment is authorized.

## AGENT RESPONSE CLASSIFICATION
No agent reports received since relaunch. Prior-session reports are treated as **PARTIALLY ACCEPTED**: their git state claims were independently verified and held, but their test totals are agent-reported prose and are pending re-verification from a committed tree.
