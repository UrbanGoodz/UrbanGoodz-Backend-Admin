# Release 2: deploy plan

Same method as release 1 (docs/qa/SAFE_DEPLOY_PLAN.md): build beside the live folder, test, swap, roll back by
renaming. Production currently runs f6c5f0d9. Rules: no secrets printed, no deleting backups or the _prev folders,
no purchase clicks, stop and report on any surprise.

## What is in it (since f6c5f0d9)
- Order Anywhere reimbursement purchasing (driver pays, uploads receipt, admin approves): OFF by default.
  Admin page /admin/urban-goodz/order-anywhere-reimbursements, driver endpoints under /api/v1/order-anywhere/driver/{id}/reimbursement.
- Monique: always on for platform admins; watches Order Anywhere, driver applications, payouts, reimbursements;
  emails urgent findings; can widen a stuck Order Anywhere search, audit applications, pre-check receipts; new
  `monique:think` (daily 04:07, ranked recommendations from real numbers).
- Skylar persona turned up (theatrical, longer entertaining replies, in-character fallbacks).
- Payment webhook returns 400 (not 200) on an invalid signature.
- Firebase rejections now log Firebase's reason.
- No composer changes. ONE new migration: 2026_11_12_000001_create_urban_goodz_order_anywhere_reimbursements
  (creates a new table only; nothing existing is altered).

## Before the swap: .env (non-secret lines, back up .env first as .env.bak-<time>)
- MONIQUE_DELAYED_ORDERS_ACTION=ask   (already the code default; set explicitly as belt and braces. Production has
  a QA order 100013 sitting "pending"; an auto-assign would grab a real driver for it.)
- Do NOT set URBAN_GOODZ_PURCHASE_METHOD yet. Reimbursement stays off (card mode) until Stripe/driver-app checks pass.
- Optional: MONIQUE_EMAIL_ALERTS=false until MAIL_FROM_ADDRESS is set (otherwise mails may fail quietly; harmless).

## Phases
0. Read-only: confirm live HEAD is still f6c5f0d9 and `git status` is clean; record counts (migrations 478, orders 5,
   users 39, delivery_men 17) and an addon_settings checksum (key_name/is_active/mode only).
1. Backups: DB dump + full app tar (including vendor/ and .env), verify both (gzip -t / tar tzf). Keep all older backups.
2. Build release_<date> beside live from the EXACT SHA given in the prompt (clone over SSH; checkout the SHA, detached).
   Copy .env, storage/app, oauth-private.key / oauth-public.key, public/Modules, recreate the public/storage symlink,
   create storage/framework/{cache/data,sessions,views}. composer install --no-dev -o with /usr/local/bin/php (8.2).
   Do NOT copy public/error_log or *.bak files.
3. Test without switching traffic (in the release folder): artisan about, route:list count, schedule:list (must now show
   monique:think at 04:07 as well as the 11 earlier jobs), migrate:status (expect exactly 1 pending), migrate --pretend
   (read it; it is one CREATE TABLE).
4. Cutover (about 2 minutes): artisan down (live) -> final DB dump -> migrate --force in release (expect exactly 1 ran)
   -> compare addon_settings checksum (must be identical) -> rename live to AdminPanel_Update_V39_prev2_<date>, release to
   AdminPanel_Update_V39 -> config:cache, route:cache, view:cache -> artisan up. Copy storage/framework/sessions from the
   previous folder so admins stay logged in.
5. Verify: see the prompt. Key checks: admin pages 200; Order Anywhere page shows the "Purchase reimbursements" button
   and its page loads (empty list); notification-preferences 401; migrations 479; no new log errors; cron still firing.

## Rollback
php artisan down in the new folder, rename folders back, php artisan up. The one migration only adds a table, so the old
code runs fine on the new schema; no DB restore needed unless the migrate step itself failed midway.

## After the deploy (separate, deliberate steps)
- Install the new Stripe webhook endpoint's signing secret in STRIPE_WEBHOOK_SECRET (user does this), config:cache,
  re-send a test webhook: expect 200 now; a wrong secret will now correctly show as 400 in Stripe.
- Approve the QA end-to-end payment test (order placed, card held, cancelled, hold released).
- Decide when to turn on reimbursement (URBAN_GOODZ_PURCHASE_METHOD=reimburse) after a phone check of the driver screen.
- Watch Monique's first day: notifications in the admin dashboard, any emails, and the first monique:think output.
