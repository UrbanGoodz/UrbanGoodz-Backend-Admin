# Urban Goodz — Emergency Production Plan

PM baseline 2026-07-25. Sequenced by dependency, not by invented clock times.

## The critical insight driving this plan

The mobile P0 recovery work and the distributed tester artifacts have **diverged**. Fixes exist on branches; testers are running builds from before those fixes. Backend fixes exist locally; production runs code from before them. Closing that gap — not writing more fixes — is the shortest path to a genuine gate.

Two rebuild/redeploy actions therefore dominate everything downstream:
1. Rebuild all three APKs from the recovery branches.
2. Deploy the 34 undeployed backend commits.

Neither is a coding task. Both are gated on owner approval.

---

## PHASE 0 — TRUTH AND STATE LOCK ✅ COMPLETE

**Owner:** PM. **Completion condition:** authoritative SHAs verified, contradictions identified.

Verified: three agents relaunched and running; branches and worktrees confirmed; live production surface probed; APK artifacts confirmed present on disk with sizes matching recorded evidence; deployed-vs-local backend delta measured at 34 commits; mock auth confirmed present in shipped Vendor APK source.

**Contradictions found:** P0-1 (APKs predate fixes; "22/22 auth assertions PASSED" validated mock auth), P0-2 (34 commits undeployed), P0-3 (Shopper branch unpushed).

---

## PHASE 1 — P0 AUDIT 🔄 IN PROGRESS

**Owner:** Lanes 1–3. **Start condition:** met. **Completion condition:** every P0 in the master matrix has direct evidence.

- Lane 1: Vendor build proof + revenue contract
- Lane 2: Driver lifecycle transitions verified against real endpoints
- Lane 3: push `00f3769`, boot loopback staging, run backend P0 suites (auth, authz, marketplace, money, document privacy)

**Blocker:** backend P0 tests have never run. Until they do, there is no evidence backend authorization or money handling is correct. This is the single largest evidence gap on the platform.

**Rollback:** none required — additive audit only.

---

## PHASE 2 — P0 REMEDIATION

**Owner:** Lanes 1–3. **Depends on:** Phase 1. **Completion:** every P0 fixed, tested, committed, pushed.

Fix only proven P0 defects. Includes P0-5 (vendor reset enumeration) and P0-6 (driver lifecycle). **Immediate action independent of everything else: push `claude-shopper-p0-recovery` (P0-3)** — one command, removes a single-point-of-failure.

**Rollback:** per-branch revert; no shared history rewritten.

---

## PHASE 3 — COMPLETE CONTROL AUDIT

**Owner:** Lanes 1–3 by surface. **Depends on:** Phase 2. **Completion:** control inventory populated, every row VERIFIED / PARTIAL / BROKEN / NOT_APPLICABLE — no row left NOT_AUDITED.

Discovery is automated, not hand-guessed: `php artisan route:list` + Blade/menu/form/AJAX extraction for web; named routes, GetX bindings, Navigator calls, and API-client/repository call sites for mobile. See `URBAN_GOODZ_COMPLETE_CONTROL_INVENTORY.md`.

This is the largest single body of remaining work and it has not started.

---

## PHASE 4 — P1 WORKFLOW COMPLETION

**Owner:** Lanes 1–3 + Production Ops. **Depends on:** Phase 3.

Order Anywhere, business package routes, load sourcing (10 adapters), medical courier chain-of-custody, service booking, Creator Commerce, Fashion Fit, payments, notifications, AI operations. **None of the nine cross-role E2E workflows in the brief has been executed end to end.**

---

## PHASE 5 — PRODUCTION HARDENING

**Owner:** Production Ops (**UNASSIGNED — staffing gate**). **Depends on:** Phase 4.

Security and RBAC verification, backups + proven restore, monitoring and alerting, queue/cron (note: queue connection is `sync` — every job runs in-request; confirm that is intended for production load), rate limits, legal pages, Play Store data-safety and privacy disclosures.

---

## PHASE 6 — FINAL RELEASE CANDIDATE

**Owner:** PM. **Depends on:** Phases 2–5.

Integrate approved commits; confirm all worktrees clean; **rebuild all three APKs/AABs from the recovery branches** (closes P0-1); deploy backend (closes P0-2); run one final certification; lock artifact hashes and source SHAs; update DCP; produce a new release manifest superseding the 2026-07-22 one.

**Rollback:** server backup `/home/urbakkej/backups/urban_goodz_deploy_20260722_074053`; restore procedure is **unproven and must be tested before it is relied upon**.

---

## PHASE 7 — PRODUCTION APPROVAL

**Owner:** D'Andre Good. **Depends on:** Phase 6.

Owner approves live payments and production rollout. Staged release, monitoring active, rollback ready.

---

## Execution order

```
PHASE 0 ✅
   │
   ├─ P0-3 push Shopper branch ────────────── (do now, no dependencies)
   │
PHASE 1 🔄 ── backend P0 tests are the long pole
   │
PHASE 2 ── P0 remediation
   │
PHASE 3 ── complete control audit  ← largest unstarted body of work
   │
PHASE 4 ── P1 workflows (9 cross-role E2E)
   │
PHASE 5 ── hardening  ← BLOCKED: no Production Ops owner
   │
PHASE 6 ── rebuild APKs + deploy backend  ← owner approval
   │
PHASE 7 ── owner approves production
```

## Dependencies

- Lane 2's delivery lifecycle depends on Lane 3 confirming or refusing the driver endpoint contracts.
- Lane 1's revenue chart depends on Lane 3 confirming whether a vendor revenue-series endpoint can exist.
- Phase 5 cannot start without a Production Ops owner.
- Phases 6 and 7 require explicit owner approval and cannot be agent-initiated.
