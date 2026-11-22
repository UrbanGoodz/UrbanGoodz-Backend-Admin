# Safe production deploy plan (no in-place merge)

Why: the production checkout has its own commits and local edits, and a direct `git merge`
there produced 25 conflicts. Resolving conflicts by hand on a live server is where bad
deploys come from. Instead: build the new version beside the old one, test it, then swap
directories. Rollback is a rename and takes under a minute.

Rules: never print or copy secrets or `.env` contents; do not delete any backup; no
purchase/upgrade clicks; stop and report instead of improvising.

## Phase 0 - read-only discovery (changes nothing)
In the app folder on the host (`.../AdminPanel_Update_V39`):
1. `git status --short | head -50` and `git log --oneline -30`
2. `git diff --stat HEAD` (uncommitted edits made directly on the server)
3. Production-only commits: `git log --oneline 30809afa..HEAD` (base commit of our work).
4. Save these outputs to `~/deploy-notes/discovery-<date>.txt` (outside the web root).
Decision gate: for every production-only commit or uncommitted edit, label it
"already in our branch" / "needs porting" / "obsolete". Port only the middle group.

## Phase 1 - backups (verify each, delete none)
1. Database: cPanel Backup or `mysqldump --single-transaction --no-tablespaces` to
   `~/backups/db-<date>.sql.gz`. Confirm file size is not tiny; `gzip -t` it.
2. Files: `tar czf ~/backups/app-<date>.tar.gz` of the whole app folder INCLUDING `.env`,
   `storage/`, and every upload folder under `public/`. `tar tzf` to confirm it lists.
3. Note free disk space first (`df -h ~`); a failed backup must stop the plan.

## Phase 2 - build the new release beside the old one
1. New folder next to the old: `~/admin.urbangoodzdelivery.com/release_<date>` (NOT the live one).
2. Put our branch code in it. Preferred: on the user's PC, `git archive` the branch
   `claude/focused-fermat-smwp94` to a zip, upload via cPanel File Manager, extract.
   (Avoids giving the server GitHub access.)
3. Copy in what must survive, without printing it: `.env`, `storage/app`, `public/storage`
   (or recreate its symlink), and every user-upload folder under `public/` that is not in git.
   Compare `ls` of the old and new `public/` to catch uploads folders.
4. Dependencies: if `composer` exists on the host, `composer install --no-dev -o` with the
   PHP 8.2 binary. If not, stop and report; do not copy a vendor folder built for another PHP.
5. Port the "needs porting" items from Phase 0 as small explicit edits; list each.

## Phase 3 - test the new release without switching traffic
In the new folder, using the same PHP 8.2 binary:
- `php artisan about` and `php artisan route:list | wc -l` (no exceptions)
- `php artisan migrate:status` then `php artisan migrate --pretend` and READ the SQL.
  The 12 migrations are additive (create table / add nullable column / add if missing).
  Anything that drops, renames or rewrites existing data: stop and report.
- `php artisan schedule:list` shows the expected jobs.
Gate: all clean, or fix before continuing.

## Phase 4 - cut over (short, reversible)
1. Announce a 5-minute window. `php artisan down` in the OLD folder (maintenance mode).
2. Final DB dump (to catch anything written since Phase 1).
3. In the NEW folder: `php artisan migrate --force`.
4. Swap by renaming: `AdminPanel_Update_V39` -> `AdminPanel_Update_V39_prev_<date>`,
   `release_<date>` -> `AdminPanel_Update_V39` (so the cPanel docroot path is unchanged).
5. In the live folder: `php artisan config:cache && php artisan route:cache && php artisan view:cache`,
   then `php artisan up`.
6. Confirm the cron entry for `schedule:run` (every minute) points at the live path.

## Phase 5 - verify
- Admin login works, no 419. Dashboard, Order Anywhere page (shows "Automatic dispatch"),
  delivery-man list, business settings: all 200.
- `GET /api/v1/urban-goodz/notification-preferences` answers 401 JSON, not a 302.
- Driver Suspend control is a POST (inspect only; do not suspend a real driver).
- `storage/logs/laravel.log` and cPanel error_log show no new errors after 5 minutes.
- `php artisan order-anywhere:audit-splits` reports mismatched=0.
- Uploaded images (logos, product photos) still load.

## Rollback (if any Phase 4-5 check fails)
1. `php artisan down` in the new folder.
2. Rename folders back (live -> `..._failed_<date>`, `..._prev_<date>` -> live).
3. `php artisan up`. The migrations only add things, so the old code runs fine on the new
   schema; restore the DB dump only if the migration step itself failed halfway.
4. Report what failed. Keep every backup and the failed folder for inspection.

## Not part of this deploy
- Reimbursement purchasing (branch `claude/order-anywhere-reimbursement`, one extra
  migration). Deploy it as a second, small release after this one is verified.
- Stripe keys/webhook: webhook URL is `/api/v1/order-anywhere/cards/stripe/webhook`.


## Lessons from the first three deployments (read before cutting over)
1. **Use scripts/ops/cutover.sh for the swap.** It is tested (tests/Feature/CutoverScriptTest.php) and encodes these rules:
   - NEVER run config:cache / route:cache / view:cache in the release folder BEFORE the swap. Laravel bakes absolute paths into
     bootstrap/cache/*.php, so after the rename the app still looks in the old folder and every request 500s (this happened in
     Release 3). The script deletes such files before the swap and builds the caches AFTER it, in the final location.
   - The rollback must never nest: it moves the failed release to a UNIQUE name (live_failed_<time>) and refuses to move anything
     onto a path that exists.
   - The release must have artisan and vendor/autoload.php at its TOP level (not nested a folder down).
   Operator steps around it: artisan down -> final DB dump -> migrate --force -> addon_settings checksum compare -> cutover.sh -> checks.
2. **The file-count cap, not gigabytes, is the limit on this host (300,000 files).** A release is about 37,000 files (vendor alone is
   26,000). After a release is verified: keep only the NEWEST rollback folder extracted; archive each older one to one verified
   .tar.gz in ~/backups (compare tar entry count with the folder file count) and remove the extracted copy. Staging counts too.
3. Run read-only database checks with the mysql client, not artisan, on the live site; keep SQL quoting simple (a quoting bug in a
   check once looked like a failed migration).

## Lesson (Release 6 prep): storage nesting

A plain recursive copy of `storage/app` into a release that already has a `storage/app` folder lands one level
deeper (`storage/app/app/...`). Releases 4 and 5 each added a level, so uploaded files ended up at
`storage/app/app/app/public` while the web server's `public/storage` symlink points at `storage/app/public`.
Cloudflare's edge cache hid it (cached images still loaded; the origin answered 302).

Rules for every release:
1. Copy runtime files with `rsync -a SRC/ DEST/` (trailing slashes on both), never `cp -r` into an existing folder.
2. After building, `find storage/app -maxdepth 4 -type d -name app` must show nothing nested.
3. Check an uploaded image **at the origin** (bypass Cloudflare, e.g. `curl --resolve`), not through the CDN.
4. The final top-up after `php artisan down` copies from live's real upload folders into `release/storage/app/public/`.

## Lessons (Releases 7-10)

1. **Build the release with a plain `git clone` (or a fetch into a fresh folder), never `git worktree add`.** A worktree
   keeps a pointer back to the main repo's path; the cutover's directory rename breaks it (`git rev-parse HEAD` fails on
   live) and the live app then depends on the rollback folder's git data.
2. **Create `storage/framework/{cache,sessions,views,testing}` (empty) before `composer install`**; a fresh clone has none
   and the composer hook fails with "Please provide a valid cache path".
3. **`public/storage` is a symlink that is not in git.** After the swap run `php artisan storage:link` if it is missing,
   and check an uploaded image at the origin (not through Cloudflare).
4. **Count rows with `SELECT COUNT(*)`**, never by counting lines of CLI table output (it wraps). Expected: migrations
   480, addon_settings 47.
5. **The newest rollback folder stays extracted** (it is the roll-back target). Archive and remove all older ones after
   every swap; each release adds ~36,000 files against the 300,000 cap.
6. Route counts change when routes are added: compare against live's count plus the named new routes, not a fixed figure.
