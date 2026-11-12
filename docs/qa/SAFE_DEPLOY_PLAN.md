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
