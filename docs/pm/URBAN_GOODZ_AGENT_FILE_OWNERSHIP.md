# Urban Goodz — Agent File Ownership

Effective 2026-07-25. No agent may edit a path owned by another. Shared files require PM assignment.

## Lane 1 — Vendor mobile
Worktree `C:\UG\UrbanGoodz_Vendor_P0_Recovery`, branch `claude-vendor-p0-recovery`
- `vendor_app/lib/**`
- `vendor_app/test/**`
- `vendor_app/android/**`
- `vendor_app/pubspec.yaml`, `pubspec.lock`
- `vendor_app/BACKEND_CONTRACTS.md` (**write owner**)
- `docs/dcp/DCP_VENDOR_*` (vendor DCP only)

## Lane 2 — Driver mobile
Worktree `C:\UG\UrbanGoodz_Driver_P0_Recovery`, branch `claude-vendor-driver-p0-recovery`
- `driver_app/lib/**`
- `driver_app/test/**`
- `driver_app/android/**`
- `driver_app/pubspec.yaml`, `pubspec.lock`
- `driver_app/BACKEND_CONTRACTS.md` (**write owner**)

## Lane 3 — Backend / database / staging
Worktree `C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Codex_Platform_Audit`, branch `claude-database-staging-recovery`
- `scripts/audit/**`
- `docs/audit/**`
- `database/baseline/**`
- Laravel app code **within the audit worktree only**
- Read-only on both `BACKEND_CONTRACTS.md` files

## Unassigned — Production Operations (needs an owner)
- deployment scripts and procedure
- backup / restore / rollback
- monitoring, logging, alerting
- cron, scheduler, queue configuration
- Firebase / push / SMTP / SMS provider evidence
- payment configuration audit evidence
- tester distribution and Play Store operational checklists

## Hard exclusions

**`C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39` (branch `adminpanel-v39-backend-sprint`) is PM-controlled.** No agent may commit to it. It holds the authoritative release evidence and DCP, and it is the deploy source. Only the PM writes `docs/pm/**` there.

**`C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz_Vendor_Driver_Sprint` (branch `vendor-driver-tester-sprint`, `c633cec`) is FROZEN.** It is the source of the currently distributed tester APKs. Do not edit; it is a release reference point.

**`UrbanGoodz2026-Revised` primary checkout** sits on `customer-tester-build-sprint` with 15 untracked audit docs. No agent may commit there — the mobile lanes work in their own worktrees.

## Concurrency rules
1. Commit early and often; never leave verified work uncommitted across a context boundary.
2. Push to your own branch only. No force-push, no rebase, no `reset --hard`, no `clean`.
3. Never edit another lane's `BACKEND_CONTRACTS.md` — append to your own; the backend lane reads both.
4. No agent deploys. Deployment is a PM action gated on owner approval.
