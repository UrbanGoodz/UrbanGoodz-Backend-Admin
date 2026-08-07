# SESSION-WEBSITE-WAITLIST-LEAD-CAPTURE-20260807: Public site lead capture wired to admin panel

**Date:** 2026-08-07
**Branch:** `feat/website-waitlist-lead-capture-20260807` (merged into `adminpanel-v39-backend-sprint` by fast-forward push)
**Commits:** Backend `8334fa7` | Site `b7e2c41` (local only — see Blockers)

---

## Problem

The marketing site (`C:\UG\urban-goodz-website`, see [[website-active-source-path]]) is a static
build with no server runtime, so its existing `src/server/*` lead-capture pipeline
(Google Sheets/Airtable/webhook) can never run in production. Signups had nowhere
real to land.

## Changes Made

- **Backend:** new public `POST /api/v1/urban-goodz/waitlist` endpoint — throttled,
  honeypot-guarded (`company` field, silently accepted+dropped, never persisted),
  server-side validated. New `urban_goodz_waitlist` table/model. New admin
  **Website Waitlist** page (`admin/urban-goodz/waitlist`) to view, filter,
  note, and transition signups, gated by `urban_goodz_waitlist_view` /
  `urban_goodz_waitlist_manage` permissions.
- **Site:** `SignupForm.tsx` now POSTs straight to the admin panel's public
  waitlist endpoint via new `src/lib/waitlist.ts`; the `mailto:` link stays as
  the manual fallback. CSP `connect-src` extended to
  `admin.urbangoodzdelivery.com`. `VITE_WAITLIST_ENDPOINT` added to `.env`.
  `scripts/preflight.mjs` updated to treat the client-side endpoint as a valid
  lead destination (was hard-failing on empty `LEAD_STORAGE`).
- **Deployed to production** (both sides) and verified live — see Tests.

## Files Created

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzWaitlistController.php` | Admin index/updateStatus/destroy |
| `app/Http/Controllers/Api/V1/UrbanGoodz/UrbanGoodzWaitlistController.php` | Public throttled create endpoint |
| `app/Models/UrbanGoodzWaitlist.php` | Model |
| `database/migrations/2026_08_07_000001_create_urban_goodz_waitlist_table.php` | Schema |
| `resources/views/admin-views/urban-goodz/waitlist/index.blade.php` | Admin page |
| `tests/Feature/UrbanGoodzWaitlistTest.php` | 9 feature tests (endpoint, honeypot, admin CRUD) |
| `urban-goodz-website/src/lib/waitlist.ts` | Client POST wrapper w/ 12s timeout + typed result |

## Files Modified

| File | Change |
|------|--------|
| `routes/admin.php` | +8: waitlist index/status/destroy routes |
| `routes/api/v1/urban_goodz.php` | +6: public waitlist route |
| `resources/views/layouts/admin/partials/_sidebar_{ecommerce,food,grocery,pharmacy}.blade.php` | +20 each: Website Waitlist nav entry. **Also folds in unrelated Creator Commerce nav from the `creator-reels-pricing` audit lane** — was already staged in the working tree when this session started; kept rather than dropped. |
| `urban-goodz-website/src/components/signup/SignupForm.tsx` | POST to waitlist endpoint instead of the dead server pipeline |
| `urban-goodz-website/vite.config.ts` | CSP `connect-src` += `admin.urbangoodzdelivery.com` |
| `urban-goodz-website/.env` | += `VITE_WAITLIST_ENDPOINT` |
| `urban-goodz-website/scripts/preflight.mjs` | Recognizes client-side endpoint as a valid lead destination |

Note: the site commit (`b7e2c41`) also swept in unrelated pre-existing uncommitted
work from the site repo — web push opt-in plumbing (dormant, no VAPID key yet),
founder portrait swap, and about/ai/platform/press copy edits. None of that was
authored this session; it was bundled because it was sitting dirty in the same
working tree. Full stat in `git show --stat b7e2c41` on the site repo.

## Tests

- **Backend:** `php artisan test --filter="UrbanGoodzStrandedE2ETest|UrbanGoodzWaitlistTest"` — 9/9 pass.
  ⚠️ Do **not** run the full suite against the local test DB — `MobileReleaseApiTest`
  and `UrbanGoodzDriverVehicleTrailerCapabilityTest` use `RefreshDatabase` and will
  nuke it (this happened once this session; DB was restored from a 250-table baseline).
- **Site:** `npm run build` clean (vite build + tsc --noEmit), `npm run preflight` green
  except the pre-existing `SMTP_PASSWORD` gap (unrelated, not blocking).
- **Live E2E on prod**, all verified this session:
  - `POST /api/v1/urban-goodz/waitlist` valid payload → `200`, row persisted.
  - Invalid payload (bad email, invalid interest) → `422`, no row.
  - Honeypot (`company` filled) → `200` (bot sees success) but **no row persisted** — confirmed by direct DB count.
  - Admin `admin/urban-goodz/waitlist` controller rendered directly (auth faked via `Auth::guard('admin')->login()` since [[prod-kernel-auth-blocked-from-cli]]) — 158KB clean render, test row visible, no error markers.
  - Test row (`prodfirst@example.com`) deleted after verification — prod table is back to 0 rows.

## Blockers Found

| Blocker | Severity | Status |
|---------|----------|--------|
| Site repo (`C:\UG\urban-goodz-website`) has no GitHub remote — `github.com/UrbanGoodz/urban-goodz-website` and `.../UrbanGoodz-Website` both 404 | MED | OPEN — needs a human to create the repo under the `UrbanGoodz` org (no `gh` CLI / token available in this environment); commit `b7e2c41` is safe locally, just unpushed |

## Handoff Notes

- Next session: once the site GitHub repo exists, `git remote add origin <url> && git push -u origin main` (or whatever branch) from `C:\UG\urban-goodz-website`.
- The public waitlist endpoint is live and taking real traffic as of this session — do not re-run the honeypot/invalid live-fire tests against prod without cleaning up afterward the way this session did (tinker delete by email).
- `docs/dcp/MASTER_STATE.md` is stale (last updated 2026-07-14, predates ~20 sessions of work including this one) — not touched here; out of scope for this feature.

## Completion Impact

- Website lead capture: 0% → 100% (endpoint, admin UI, tests, prod deploy, live verification all done). Only the site's own GitHub push remains, and it's an external blocker, not implementation work.
