# Ops safety net: staging, backups, error tracking

Why: everything has been deployed straight to production (build beside, test, swap). That worked, but the only
rehearsal was the live site. These three pieces make the next mistake cheap.

## 1. Error tracking (Sentry, free tier is enough to start)
Code is in (sentry/sentry-laravel, config/sentry.php, hooked in bootstrap/app.php). It is OFF until a DSN is set.
Owner: create a Sentry project (platform: Laravel), copy its DSN, and put it in the production .env as
SENTRY_LARAVEL_DSN=... (+ SENTRY_ENVIRONMENT=production). Then `php artisan config:cache`.
Privacy: no cookies, IPs or user details are sent; SQL values are not attached; tracing is off. 404s, validation
errors, auth failures and CSRF expiries are ignored on purpose.
Check: `php artisan tinker --execute="\Sentry\captureMessage('ug test');"` shows up in the Sentry dashboard.

## 2. Backups with an off-server copy
scripts/ops/backup.sh: DB dump + user files, both verified (gzip -t, table count, completion marker), a local
copy under ~/backups, and an optional copy off the server through rclone.
- Cron (daily, off-peak):  `17 4 * * * bash /home/<cpanel-user>/<app>/scripts/ops/backup.sh /home/<cpanel-user>/<app> >/dev/null 2>&1`
- Off-host: install rclone in the home folder (single binary, no root), then the OWNER runs `rclone config` once to
  link a storage account (Google Drive, Backblaze B2, S3, Dropbox ...). Then create ~/.ug-backup.env containing only
  `BACKUP_RCLONE_REMOTE=remotename:ug-backups`. The script never reads the rclone credentials.
- Retention: automatic backups older than 21 days are pruned, never below 10 of each kind. Deploy backups
  (db-r2-*, app-*) are not touched (different names).
- Restore test (do it once now, then quarterly): download the newest db-*.sql.gz, `gzip -t`, and import it into a
  scratch database on the STAGING site; confirm logins and a few orders.

## 3. Staging
A copy of the site on its own address, its own database, test-mode payments, no real customers.
Setup (all in cPanel):
1. Subdomain staging.admin.urbangoodzdelivery.com with its own folder, e.g. ~/staging.admin.urbangoodzdelivery.com/app.
2. New MySQL database named with "staging" in it (e.g. urbakkej_staging) + its own database user.
3. Put the code there the same way as production (build from the exact SHA; composer install --no-dev -o).
4. .env for staging: APP_ENV=staging, APP_DEBUG=false, its own APP_KEY and DB_*, MAIL_MAILER=log, Stripe TEST keys only,
   URBAN_GOODZ_PAYMENT_MODE=sandbox, no FCM project (or a test one), SENTRY_ENVIRONMENT=staging.
5. Load a production dump into the staging database, then run `php artisan staging:sanitize --force`.
   It REFUSES to run unless APP_ENV=staging AND the database name contains "staging". It anonymises users, drivers,
   vendors, Order Anywhere and Stranded personal data, removes device and login tokens and conversation text.
6. From then on: every release is deployed to staging first, the verification list is run there, and only then to
   production.
