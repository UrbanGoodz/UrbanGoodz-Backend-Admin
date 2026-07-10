# Urban Goodz Tester Release — Release Control Document

**Lane:** UG-PM-00
**Mode:** Deadlock Execution
**Date:** July 10, 2026
**Status:** Release-control setup complete

---

## 1. Release Objective

Establish a safe parallel Git structure for the Urban Goodz tester-ready Android release. This lane sets baselines, creates isolated worktrees/branches, identifies all application repositories, and produces this release-control ledger that every other session must follow.

This lane does NOT implement features.

---

## 2. Source Repository, Branch, and Starting Commit

| Field | Value |
|---|---|
| Source Repository | `C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39` |
| Git Root | `C:/Users/D'Andre Good/Documents/GitHub/AdminPanel_Update_V39` |
| Source Branch | `adminpanel-v39-backend-sprint` |
| Starting Commit | `d0c8c67e4d3499464d93f490df1fbc33e8a9d2d5` |
| Remote | `origin` → `https://github.com/UrbanGoodz/back-end.git` |
| Working-Tree Status | **CLEAN** (no uncommitted changes) |

### Key History Verification
- Commit `8d4bec2` (Addendum D: Vehicle taxonomy, trailer capabilities, load-board eligibility) — **CONFIRMED** in history
- Commit `8054958` (Add backend source commit map for sprint documentation) — **CONFIRMED** in history
- Head commit `d0c8c67` (Fix LoginController ReCaptcha + employee login logic, clean .gitignore for sprint artifacts) — **CONFIRMED** as HEAD

---

## 3. Worktree / Branch Table

All worktrees created from the same source HEAD commit: `d0c8c67e4d3499464d93f490df1fbc33e8a9d2d5`

| Lane ID | Branch | Worktree Path | Starting Commit | Status |
|---|---|---|---|---|
| Source | `adminpanel-v39-backend-sprint` | `C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39` | `d0c8c67` | Clean, verified |
| UG-BE-01 | `sprint/be-auth-mail` | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz-Parallel-Sprint\BE-Auth-Mail` | `d0c8c67` | Created, clean |
| UG-BE-02 | `sprint/be-notifications-security` | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz-Parallel-Sprint\BE-Notifications-Security` | `d0c8c67` | Created, clean |
| UG-BE-03 | `sprint/be-payments-order-anywhere` | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz-Parallel-Sprint\BE-Payments-OrderAnywhere` | `d0c8c67` | Created, clean |
| UG-BE-04 | `sprint/be-maps-scopes-routes` | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz-Parallel-Sprint\BE-Maps-Scopes-Routes` | `d0c8c67` | Created, clean |

### Existing Customer App Worktrees (READ-ONLY REFERENCE)
The customer app (`UrbanGoodz2026-Revised`) already has active worktrees. These are NOT created by this lane:

| Branch | Path | Commit |
|---|---|---|
| `agents/gradual-halibut` | `UrbanGoodz2026-Revised.worktrees/agents-gradual-halibut` | `cb99cac` |
| `agents/pre-checks-branch-status-qa-validation` | `UrbanGoodz2026-Revised.worktrees/agents-pre-checks-branch-status-qa-validation` | `cb99cac` |
| `agents/repo-inspection-backend-check` | `UrbanGoodz2026-Revised.worktrees/agents-repo-inspection-backend-check` | `cb99cac` |
| `agents/urban-goodz-qa-fix-verification` | `UrbanGoodz2026-Revised.worktrees/agents-urban-goodz-qa-fix-verification` | `cb99cac` |
| `agents/urban-goodz-qa-fix-verification-e8b09e41` | `UrbanGoodz2026-Revised.worktrees/agents-urban-goodz-qa-fix-verification-e8b09e41` | `cb99cac` |

---

## 4. Client Repository Inventory

### 4.1 Urban Goodz Customer (ACTIVE)

| Field | Value |
|---|---|
| Absolute Path | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz2026-Revised` |
| Git Root | `C:/Users/D'Andre Good/Documents/GitHub/UrbanGoodz2026-Revised` |
| Current Branch | `customer-tester-build-sprint` |
| Current Commit | `9e000aae633be0281e534f2381da5b4b60c92cee` |
| Remote | `origin` → `https://github.com/UrbanGoodz/UrbanGoodz2026-Revised.git` |
| Working-Tree Status | **DIRTY** — modified: `OPEN_CODE_STATUS.md`; untracked: `android/app/google-services.pre-rc2-backup.json`, `build_status.txt`, 2 staging spec docs, 3 APK outputs, 2 output directories, 1 PHP tool |
| pubspec.yaml | YES |
| android/app | YES |
| Active Version | YES — confirmed as the authoritative customer app |
| Existing Release/Test Branch | YES — `customer-tester-build-sprint` (current) |

**Uncommitted Files Classification:**
- `OPEN_CODE_STATUS.md` — release-related status doc
- `android/app/google-services.pre-rc2-backup.json` — secret/config backup (DO NOT COMMIT)
- `build_status.txt` — generated runtime artifact
- `lib/features/urban_goodz/BACKEND_STAGING_FILTER_SPEC.md` — release-related spec doc
- `lib/features/urban_goodz/STAGING_VISIBILITY_GUARD_PLAN.md` — release-related spec doc
- `outputs/UrbanGoodz_Customer_Firebase_RC3.apk` — generated APK (DO NOT COMMIT)
- `outputs/UrbanGoodz_Customer_Tester_RC1.apk` — generated APK (DO NOT COMMIT)
- `outputs/UrbanGoodz_Customer_Tester_RC2.apk` — generated APK (DO NOT COMMIT)
- `outputs/import-review-gate/` — generated output directory
- `outputs/urban_goodz_p2b_final_dry_run_reports/` — generated output directory
- `tools/urban_goodz_p2b_report.php` — utility script

### 4.2 Urban Goodz Driver (LOCAL COPY — NO GIT)

| Field | Value |
|---|---|
| Absolute Path | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz_Driver_App` |
| Git Root | **NONE** — not a git repository |
| Current Branch | N/A |
| Current Commit | N/A |
| Remote | N/A |
| Working-Tree Status | N/A |
| pubspec.yaml | YES |
| android/app | YES |
| Active Version | UNCERTAIN — appears to be a local Flutter project copy without version control |
| Existing Release/Test Branch | N/A |
| App/Project Name | `urban_goodz_driver` |

### 4.3 Urban Goodz Vendor (LOCAL COPY — NO GIT)

| Field | Value |
|---|---|
| Absolute Path | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz_Vendor_App` |
| Git Root | **NONE** — not a git repository |
| Current Branch | N/A |
| Current Commit | N/A |
| Remote | N/A |
| Working-Tree Status | N/A |
| pubspec.yaml | YES |
| android/app | YES |
| Active Version | UNCERTAIN — appears to be a local Flutter project copy without version control |
| Existing Release/Test Branch | N/A |
| App/Project Name | `urban_goodz_vendor` |

### 4.4 Urban Goodz Business (NOT FOUND AS STANDALONE)

| Field | Value |
|---|---|
| Standalone Flutter/Android App | **NOT FOUND** |
| PWA/Web Portal | **NOT FOUND** |
| WebView Wrapper | **NOT FOUND** |
| Backend-Integrated Portal | YES — Business Portal is part of the backend (`AdminPanel_Update_V39`). Features include: Business client portal with auth, admin CRUD, views, and `BusinessMiddleware` (commit `aa6795f`). |
| Absent/Unknown Status | No standalone Business APK project exists under `C:\Users\D'Andre Good\Documents\GitHub\`. The Business Portal lives within the Laravel backend as server-rendered views. |
| Architecture Decision | **DO NOT BUILD** a standalone Business app in this lane. Business functionality is served by the backend admin panel. |

---

## 5. Duplicate / Legacy Project Warnings

| Candidate | Path | Remote | Branch | Status | Warning |
|---|---|---|---|---|---|
| `2026UrbanGoodz` | `C:\Users\D'Andre Good\Documents\GitHub\2026UrbanGoodz` | `UrbanGoodz/2026UrbanGoodz.git` | `main` | Clean | Legacy/extracted repo. Contains `customer_app/` and old codecanyon zips. Different remote from active customer app. **Do not use.** |
| `UserApp_Backup_Before_V39_Merge` | `C:\Users\D'Andre Good\Documents\GitHub\UserApp_Backup_Before_V39_Merge` | `UrbanGoodz/UrbanGoodz2026-Revised.git` | `main` | Dirty (deleted cmake/build files) | Backup copy on `main` branch. Same remote as active customer but different branch. **Do not use.** |
| `UrbanGoodz2026 Revised` (with space) | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz2026 Revised` | `UrbanGoodz/UrbanGoodz2026-Revised.git` | `customer-tester-build-sprint` | Has worktrees | Duplicate of `UrbanGoodz2026-Revised` with a space in the name. Same remote, same branch. **Use `UrbanGoodz2026-Revised` (no space) as canonical.** |
| `UrbanGoodz-App` | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz-App` | N/A | N/A | Not a git repo | Only contains `lib/` directory. Incomplete fragment. **Do not use.** |
| `UrbanGoodz-Run` | `C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz-Run` | N/A | N/A | Not a git repo | Only contains `lib/` directory. Incomplete fragment. **Do not use.** |

---

## 6. File Ownership Rules

### Lane Ownership Assignments

| Lane ID | Scope |
|---|---|
| **UG-BE-01** | Admin authentication/login implementation and tests; mail configuration/runtime loading; SMTP test-send functionality; related documentation only |
| **UG-BE-02** | FCM/device-token services; notification events/jobs/listeners; in-app notifications; Pusher/realtime; Email OTP; TOTP; optional SMS provider configuration (disabled by default); related tests and documentation |
| **UG-BE-03** | Payment gateway implementations; Order Anywhere backend; payment webhooks; related tests and documentation |
| **UG-BE-04** | Maps integration; Google Maps scopes; route management and optimization; dedicated routes; manifests; related tests and documentation |
| **UG-APP-03** | READ-ONLY inventory initially; may run safe diagnostic/build commands; must not modify shared backend files; must not build missing Business architecture without approval |

### Shared Files (One Owner at a Time)

The following files require exclusive ownership. Any lane needing a shared file owned by another lane must report the dependency, avoid editing it, and wait for Release Control to assign ownership:

- `composer.json`
- `composer.lock`
- `routes/*.php` (all route files)
- Service providers
- Central configuration files (`config/*.php`)
- Shared helpers
- Shared environment examples (`.env.example`)

---

## 7. Dependency and Merge Order

Lanes must merge in this exact sequence. No lane may merge ahead of its predecessor:

1. **BE Auth/Mail** (`sprint/be-auth-mail`)
2. **BE Notifications/Security** (`sprint/be-notifications-security`)
3. **BE Maps/Scopes/Routes** (`sprint/be-maps-scopes-routes`)
4. **BE Payments/Order Anywhere** (`sprint/be-payments-order-anywhere`)
5. **Customer App** (parallel — no backend merge dependency)
6. **Driver App** (parallel — no backend merge dependency)
7. **Vendor App** (parallel — no backend merge dependency)
8. **Business App** (if approved — depends on backend Business Portal)
9. **Integrated E2E Fixes** (after all backend and app lanes merge)
10. **Final APK Release Commit**

---

## 8. Commit-and-Push Completion Gate

A lane task is NOT COMPLETE until ALL of the following are true:

1. **Tested or validated** — unit tests pass, or manual validation documented
2. **Committed** — at least one commit exists on the lane branch
3. **Pushed** — commit is pushed to `origin` on the lane branch
4. **Reported** — commit hash is reported to Release Control
5. **Preserved** — DCP (Digital Commit Preservation) is run

If any of these are missing, report **NOT COMPLETE**.

---

## 9. DCP Requirements

Every lane must run DCP after completing work. DCP captures:
- Lane ID and status
- Source and destination paths
- Branch names and commit hashes
- Working-tree status at time of capture
- Any blockers or deviations
- Next actions

DCP command pattern: `/dcp-compress <lane-id> <status-summary>`

---

## 10. Prohibited Production Actions

The following actions are **STRICTLY PROHIBITED** across all lanes:

- Deploy to production
- Merge to `main`
- Run database migrations
- Run live payment transactions
- Enable paid SMS
- Reset any branch
- Stash any work
- Force push
- Use `git add .`
- Delete user files
- Modify application code outside lane scope
- Print secrets, tokens, or credentials
- Commit `.env` files, signing files, service-account JSON, tokens, runtime data, or APKs
- Create multiple worktrees pointing to the same branch
- Run `route:cache`

---

## 11. Current Risks and Blockers

| Risk/Blocker | Severity | Impact | Mitigation |
|---|---|---|---|
| **Driver App has no git repo** | HIGH | Cannot create isolated worktrees or track changes for Driver lane | Driver App must be initialized as a git repo and pushed to a remote before Driver lane can operate. UG-APP-03 must identify or establish the authoritative Driver repo. |
| **Vendor App has no git repo** | HIGH | Same as Driver | Same mitigation required. |
| **Business App does not exist as standalone** | MEDIUM | No separate Business lane can operate independently | Business functionality is backend-integrated. If a standalone Business app is needed, it must be architected and approved before building. |
| **Customer App working tree is dirty** | LOW | Multiple uncommitted files exist in the active customer repo | These are classified and documented. No action needed unless merging. Do not stage or commit without lane authorization. |
| **Existing customer worktrees on agents/* branches** | LOW | Pre-existing agent worktrees may cause confusion | Documented in Section 3. Do not modify or delete these. They are separate from backend sprint worktrees. |

---

## 12. Status Ledger

| Phase | Status | Details |
|---|---|---|
| Phase 1: Backend Baseline | COMPLETE | Branch `adminpanel-v39-backend-sprint` confirmed. Commit `d0c8c67` as HEAD. Commit `8d4bec2` verified in history. Remote origin correct. Working tree clean. No pre-existing worktrees. |
| Phase 2: Client Projects | COMPLETE | Customer: `UrbanGoodz2026-Revised` (active). Driver: `UrbanGoodz_Driver_App` (no git). Vendor: `UrbanGoodz_Vendor_App` (no git). Business: Not a standalone app. 5 duplicate/legacy candidates identified. |
| Phase 3: Backend Worktrees | COMPLETE | 4 worktrees created at `d0c8c67`. All verified clean, correct branch, correct commit, correct remote. |
| Phase 4: Release-Control Document | COMPLETE | This document. Created at `docs/release/URBAN_GOODZ_TESTER_RELEASE_CONTROL.md`. |
| Phase 5: Validation | PENDING | See Phase 5 section below. |
| Phase 6: Commit and Push | PENDING | See Phase 6 section below. |

---

## 13. Validation Commands

After creating this document, run in the backend source repo:

```bash
git diff --check
git diff --stat
git status --short
```

Expected: Only `docs/release/URBAN_GOODZ_TESTER_RELEASE_CONTROL.md` should appear as untracked. No other files should be modified.

---

## 14. Accepted Backend Work (DO NOT REBUILD)

The following work is accepted and must be preserved in all lanes:

- Backend recovery through commit `8054958`
- Driver vehicle/trailer/load-board backend commit `8d4bec2`
- Existing payment foundations (commit `bb84832`, `470ce69`)
- AI copilot foundations (commit `c4ccbfe`)
- Creator Commerce foundations (commit `9e9f4a2`)
- Business Portal foundations (commit `aa6795f`)
- Dedicated Routes and manifests (commit `531d253`)
- Scanning and age compliance (commit `531d253`)
- Driver API and earnings (commit `07da6f7`)
- Sourcing foundations (commit `ed9f73a`)
- Admin panel routes, controllers, models (commit `c4c590c`)
- Driver API security tests (commit `bc3502d`)
- SMTP fix and diagnostics (commit `f66d7a1`)
- ConfigServiceProvider fix (commit `a24cc1f`)
- LoginController ReCaptcha fix (commit `d0c8c67`)
